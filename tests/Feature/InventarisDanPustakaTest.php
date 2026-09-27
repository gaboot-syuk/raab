<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Pustaka\BukuSiapDiambil;
use App\Notifications\Pustaka\KabarPinjaman;
use App\Services\Inventaris;
use App\Services\Peminjaman;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase 6 — Inventaris & Perpustakaan.
 *
 * EMPAT JANJI YANG DIJAGA DI SINI:
 *  1. Stok aset & jumlah eksemplar selalu konsisten setelah pinjam/kembali.
 *  2. Antrian otomatis naik saat buku dikembalikan.
 *  3. Barang rusak/hilang tercatat sebagai mutasi + penanggung jawab.
 *  4. Kader & alumni bisa meminjam dari dashboard masing-masing.
 *  5. Tidak ada denda di mana pun.
 */
class InventarisDanPustakaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            SocialLinkSeeder::class,
        ]);
    }

    private function pengurus(string $peran = 'sekretaris'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    private function anggota(string $nama = 'Siti Aminah'): Member
    {
        $user = User::factory()->create([
            'name' => $nama,
            'email' => \Illuminate\Support\Str::slug($nama).'@contoh.test',
            'email_verified_at' => now(),
        ]);

        $member = new Member;
        $member->user_id = $user->id;
        $member->nama_lengkap = $nama;
        $member->status = Member::STATUS_AKTIF;
        $member->jalur = Member::JALUR_KADER;
        $member->angkatan = 2024;
        $member->save();

        return $member;
    }

    private function aset(int $jumlah = 10, array $ganti = []): InventoryItem
    {
        // Kategori dibuat sekali saja per tes: slug-nya unik, jadi helper ini
        // tidak boleh membuatnya berulang.
        $kategori = InventoryCategory::query()->firstWhere('slug', 'peralatan');

        if (! $kategori) {
            $kategori = new InventoryCategory;
            $kategori->slug = 'peralatan';
            $kategori->urutan = 1;
            $kategori->aktif = true;
            // Translasi diisi SEBELUM save (kolom JSON NOT NULL).
            $kategori->setTranslations('nama', ['id' => 'Peralatan']);
            $kategori->save();
        }

        $item = new InventoryItem;
        $item->category_id = $kategori->id;
        $item->kode = $ganti['kode'] ?? 'INV-001';
        $item->satuan = 'buah';
        $item->jumlah = $jumlah;
        $item->jumlah_minimum = $ganti['jumlah_minimum'] ?? 0;
        $item->kondisi = InventoryItem::KONDISI_BAIK;
        $item->nilai = 150000;
        $item->is_public = $ganti['is_public'] ?? true;
        $item->aktif = true;
        $item->setTranslations('nama', ['id' => $ganti['nama'] ?? 'Sound System']);
        $item->save();

        return $item;
    }

    private function buku(int $eksemplar = 1, array $ganti = []): Book
    {
        $judul = $ganti['judul'] ?? 'Sistem Politik Indonesia';

        $book = new Book;
        $book->penulis = $ganti['penulis'] ?? 'Miriam Budiardjo';
        $book->penerbit = 'Gramedia';
        $book->isbn = $ganti['isbn'] ?? '978-979-1234';
        $book->kategori = 'Politik';
        $book->is_public = true;
        $book->aktif = true;
        $book->setTranslations('judul', ['id' => $judul]);
        $book->setTranslations('slug', ['id' => Book::slugUnik($judul)]);
        $book->save();

        for ($i = 1; $i <= $eksemplar; $i++) {
            $salinan = new BookCopy;
            $salinan->book_id = $book->id;
            $salinan->kode_eksemplar = 'BK-'.str_pad((string) $book->id, 3, '0', STR_PAD_LEFT).'-'.$i;
            $salinan->kondisi = 'baik';
            $salinan->status = BookCopy::STATUS_TERSEDIA;
            $salinan->save();
        }

        return $book->fresh();
    }

    /* ===================== 3. Mutasi aset ===================== */

    public function test_barang_masuk_menambah_stok_dan_meninggalkan_jejak(): void
    {
        $aset = $this->aset(5);
        $petugas = $this->pengurus();

        app(Inventaris::class)->catat($aset, InventoryMovement::JENIS_MASUK, 3, ['catatan' => 'Hibah alumni'], $petugas);

        $aset->refresh();

        $this->assertSame(8, $aset->jumlah);

        $mutasi = $aset->mutasi()->firstOrFail();
        $this->assertSame(5, $mutasi->jumlah_sebelum);
        $this->assertSame(8, $mutasi->jumlah_sesudah);
        $this->assertSame(InventoryMovement::JENIS_MASUK, $mutasi->jenis);
    }

    public function test_stok_tidak_pernah_bisa_menjadi_negatif(): void
    {
        $aset = $this->aset(2);

        $this->expectException(ValidationException::class);

        app(Inventaris::class)->catat($aset, InventoryMovement::JENIS_KELUAR, 5);

        $this->assertSame(2, $aset->fresh()->jumlah);
    }

    public function test_barang_rusak_wajib_menyebut_penanggung_jawab(): void
    {
        $aset = $this->aset(4);

        try {
            app(Inventaris::class)->catat($aset, InventoryMovement::JENIS_RUSAK, 1);
            $this->fail('Seharusnya menolak mutasi rusak tanpa penanggung jawab.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('penanggung_jawab_nama', $e->errors());
        }

        $this->assertSame(4, $aset->fresh()->jumlah, 'Stok tidak boleh berubah saat mutasi ditolak.');
        $this->assertSame(0, $aset->mutasi()->count());
    }

    public function test_barang_rusak_dengan_penanggung_jawab_mengurangi_stok(): void
    {
        $aset = $this->aset(4);
        $kader = $this->anggota('Bagas Prakoso');

        app(Inventaris::class)->catat(
            $aset,
            InventoryMovement::JENIS_RUSAK,
            1,
            ['penanggung_jawab_id' => $kader->id, 'catatan' => 'Terjatuh saat kegiatan'],
        );

        $aset->refresh();

        $this->assertSame(3, $aset->jumlah);
        $this->assertSame(InventoryItem::KONDISI_RUSAK_RINGAN, $aset->kondisi);

        $mutasi = $aset->mutasi()->firstOrFail();
        $this->assertSame('Bagas Prakoso', $mutasi->namaPenanggungJawab());
    }

    public function test_barang_hilang_mengurangi_stok_menjadi_habis(): void
    {
        $aset = $this->aset(1);

        app(Inventaris::class)->catat(
            $aset,
            InventoryMovement::JENIS_HILANG,
            1,
            ['penanggung_jawab_nama' => 'Petugas Luar'],
        );

        $aset->refresh();

        $this->assertSame(0, $aset->jumlah);
        $this->assertSame(InventoryItem::KONDISI_RUSAK_BERAT, $aset->kondisi);
    }

    public function test_perbaikan_mengembalikan_stok_dan_kondisi(): void
    {
        $aset = $this->aset(2);
        $services = app(Inventaris::class);

        $services->catat($aset, InventoryMovement::JENIS_RUSAK, 1, ['penanggung_jawab_nama' => 'Bagas']);
        $services->catat($aset, InventoryMovement::JENIS_PERBAIKAN, 1, ['catatan' => 'Selesai diperbaiki']);

        $this->assertSame(2, $aset->fresh()->jumlah);
        $this->assertSame(InventoryItem::KONDISI_BAIK, $aset->fresh()->kondisi);
    }

    public function test_katalog_inventaris_publik_hanya_menampilkan_barang_publik(): void
    {
        $this->aset(3, ['nama' => 'Sound System', 'is_public' => true]);
        $this->aset(3, ['nama' => 'Berkas Sekretariat', 'is_public' => false, 'kode' => 'INV-002']);

        $this->get('/inventaris')
            ->assertOk()
            ->assertSee('Sound System', false)
            ->assertDontSee('Berkas Sekretariat', false);
    }

    /* ===================== Katalog buku ===================== */

    public function test_katalog_perpustakaan_dapat_dicari(): void
    {
        $this->buku(2, ['judul' => 'Sistem Politik Indonesia']);
        $this->buku(1, ['judul' => 'Sosiologi Hukum', 'penulis' => 'Satjipto Rahardjo', 'isbn' => '978-979-9999']);

        $this->get('/perpustakaan')->assertOk()->assertSee('Sistem Politik Indonesia', false);

        $this->get('/perpustakaan?cari=Budiardjo')
            ->assertOk()
            ->assertSee('Sistem Politik Indonesia', false)
            ->assertDontSee('Sosiologi Hukum', false);

        $this->get('/perpustakaan?cari=978-979-1234')
            ->assertOk()
            ->assertSee('Sistem Politik Indonesia', false);    }

    public function test_halaman_detail_buku_dapat_dibuka(): void
    {
        $buku = $this->buku(1);

        $this->get('/perpustakaan/'.$buku->getTranslation('slug', 'id'))
            ->assertOk()
            ->assertSee('Sistem Politik Indonesia', false);
    }

    /* ===================== 1. Stok selalu konsisten ===================== */

    public function test_eksemplar_terkunci_sejak_disetujui_dan_kembali_saat_dikembalikan(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $this->assertSame(1, $buku->eksemplarTersedia());

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $this->assertSame(Loan::STATUS_DIAJUKAN, $pinjaman->status);

        $services->setujui($pinjaman, $petugas);
        $this->assertSame(0, $buku->fresh()->eksemplarTersedia(), 'Eksemplar harus terkunci sejak disetujui.');

        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');
        $this->assertSame(Loan::STATUS_DIPINJAM, $pinjaman->fresh()->status);
        $this->assertSame(0, $buku->fresh()->eksemplarTersedia());

        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        $this->assertSame(Loan::STATUS_DIKEMBALIKAN, $pinjaman->fresh()->status);
        $this->assertSame(1, $buku->fresh()->eksemplarTersedia(), 'Eksemplar harus kembali tersedia.');
        $this->assertSame(BookCopy::STATUS_TERSEDIA, $pinjaman->fresh()->eksemplar->status);
    }

    public function test_buku_rusak_berat_saat_kembali_masuk_perbaikan(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');
        $services->kembalikan($pinjaman->fresh(), $petugas, 'rusak_berat');

        $this->assertSame(BookCopy::STATUS_PERBAIKAN, $pinjaman->fresh()->eksemplar->status);
        $this->assertSame(0, $buku->fresh()->eksemplarTersedia(), 'Eksemplar rusak tidak dihitung tersedia.');
    }

    public function test_peminjaman_ditolak_mengembalikan_eksemplar(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $this->assertSame(0, $buku->fresh()->eksemplarTersedia());

        $services->tolak($pinjaman->fresh(), $petugas, 'Judul ini untuk keperluan lomba.');

        $this->assertSame(Loan::STATUS_DITOLAK, $pinjaman->fresh()->status);
        $this->assertSame(1, $buku->fresh()->eksemplarTersedia());
    }

    /* ===================== 2. Antrian otomatis naik ===================== */

    public function test_antrian_naik_otomatis_saat_buku_dikembalikan(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        // Semua eksemplar habis → peminjam kedua masuk antrian, bukan meminjam.
        $reservasi = $services->antri($buku, $kedua);
        $this->assertSame(BookReservation::STATUS_MENUNGGU, $reservasi->status);
        $this->assertSame(1, $reservasi->posisi);

        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        $reservasi->refresh();

        $this->assertSame(BookReservation::STATUS_SIAP, $reservasi->status, 'Antrian harus naik otomatis.');
        $this->assertNotNull($reservasi->kedaluwarsa_pada);
        $this->assertNotNull($reservasi->siap_pada);

        Notification::assertSentOnDemand(
            BukuSiapDiambil::class,
            fn (BukuSiapDiambil $n): bool => $n->reservasi->id === $reservasi->id,
        );
    }

    /**
     * Jatah 48 jam di BookReservation tidak ada artinya kalau orang lain masih
     * bisa menyambar eksemplarnya lebih dulu dari katalog publik.
     */
    public function test_eksemplar_yang_ditahan_untuk_antrian_tidak_dapat_disambar_orang_lain(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $ketiga = $this->anggota('Peminjam Ketiga');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $services->antri($buku, $kedua);
        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        // Eksemplar sudah kembali ke rak, tetapi masih ditahan untuk peminjam kedua.
        $this->assertSame(1, $buku->fresh()->eksemplarTersedia());
        $this->assertSame(0, $buku->fresh()->eksemplarBebas(), 'Eksemplar harus dihitung sebagai ditahan, bukan bebas.');

        $this->expectException(ValidationException::class);
        $services->ajukanBuku($buku->fresh(), [], $ketiga);
    }

    public function test_pemegang_giliran_tetap_dapat_mengambil_eksemplar_yang_ditahan_untuknya(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $reservasi = $services->antri($buku, $kedua);
        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        // Walau hitungan "bebas" nol, pemegang giliran harus tetap bisa meminjam.
        $pinjamanKedua = $services->ajukanBuku($buku->fresh(), [], $kedua);

        $this->assertSame(Loan::STATUS_DIAJUKAN, $pinjamanKedua->status);
        $this->assertSame($kedua->id, $pinjamanKedua->member_id);

        // Reservasinya dianggap terpenuhi begitu pengajuan dibuat.
        $this->assertSame(BookReservation::STATUS_DIAMBIL, $reservasi->fresh()->status);
    }

    public function test_anggota_tidak_dapat_mengantri_dua_kali_untuk_judul_yang_sama(): void
    {
        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $services->antri($buku, $kedua);

        try {
            $services->antri($buku, $kedua);
            $this->fail('Seharusnya menolak antrian ganda.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('buku', $e->errors());
        }

        $this->assertSame(1, BookReservation::query()->where('member_id', $kedua->id)->count());
    }

    public function test_antrian_yang_kedaluwarsa_dilewati_dan_berikutnya_naik(): void
    {
        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $ketiga = $this->anggota('Peminjam Ketiga');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $services->antri($buku, $kedua);
        $services->antri($buku, $ketiga);

        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        // Giliran kedua sudah "siap" tetapi tidak diambil sampai lewat batas.
        $reservasiKedua = BookReservation::query()->where('member_id', $kedua->id)->firstOrFail();
        $this->assertSame(BookReservation::STATUS_SIAP, $reservasiKedua->status);

        $reservasiKedua->forceFill(['kedaluwarsa_pada' => now()->subHour()])->save();

        $dilewati = $services->lewatiKedaluwarsa();

        $this->assertSame(1, $dilewati);
        $this->assertSame(BookReservation::STATUS_KEDALUWARSA, $reservasiKedua->fresh()->status);
        $this->assertSame(
            BookReservation::STATUS_SIAP,
            BookReservation::query()->where('member_id', $ketiga->id)->firstOrFail()->status,
            'Antrian berikutnya harus naik setelah yang di depan dilewati.',
        );
    }

    public function test_anggota_dapat_mengantri_ulang_setelah_antriannya_kedaluwarsa(): void
    {
        $buku = $this->buku(1);
        $pertama = $this->anggota('Peminjam Pertama');
        $kedua = $this->anggota('Peminjam Kedua');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $pertama);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $reservasi = $services->antri($buku, $kedua);
        $reservasi->forceFill(['status' => BookReservation::STATUS_KEDALUWARSA])->save();

        // Tidak boleh terkunci oleh indeks unik — inilah alasan indeksnya tidak
        // dibuat unik pada migrasi.
        $baru = $services->antri($buku, $kedua);

        $this->assertNotSame($reservasi->id, $baru->id);
        $this->assertSame(BookReservation::STATUS_MENUNGGU, $baru->status);
    }

    /* ===================== Peminjaman eksternal ===================== */

    public function test_peminjaman_eksternal_wajib_punya_penanggung_jawab_internal(): void
    {
        $buku = $this->buku(1);

        try {
            app(Peminjaman::class)->ajukanBuku($buku, ['peminjam_nama' => 'Tamu Universitas'], null);
            $this->fail('Seharusnya menolak peminjaman eksternal tanpa penanggung jawab.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('penanggung_jawab_id', $e->errors());
        }

        $this->assertSame(0, Loan::query()->count());
    }

    public function test_peminjaman_eksternal_berhasil_dengan_penanggung_jawab(): void
    {
        $buku = $this->buku(1);
        $penanggungJawab = $this->anggota('Penanggung Jawab');

        $pinjaman = app(Peminjaman::class)->ajukanBuku($buku, [
            'peminjam_nama' => 'Tamu Universitas',
            'peminjam_kontak' => '08123456789',
            'peminjam_instansi' => 'Universitas Sebelas Maret',
            'penanggung_jawab_id' => $penanggungJawab->id,
        ], null);

        $this->assertSame(Loan::JENIS_EKSTERNAL, $pinjaman->jenis);
        $this->assertNull($pinjaman->member_id);
        $this->assertSame('Tamu Universitas', $pinjaman->namaPeminjam());
        $this->assertSame($penanggungJawab->id, $pinjaman->penanggung_jawab_id);
    }

    /* ===================== Perpanjangan ===================== */

    public function test_perpanjangan_dibatasi_satu_kali(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $lama = $pinjaman->fresh()->jatuh_tempo;

        $perpanjangan = $services->ajukanPerpanjangan($pinjaman->fresh(), 'Masih dipakai untuk tugas.');
        $services->setujuiPerpanjangan($perpanjangan, $petugas);

        $baru = $pinjaman->fresh()->jatuh_tempo;

        $this->assertTrue($baru->greaterThan($lama), 'Jatuh tempo harus mundur setelah perpanjangan.');
        $this->assertSame(1, $pinjaman->fresh()->perpanjangan_ke);

        // Permohonan kedua ditolak karena batasnya satu kali.
        $this->expectException(ValidationException::class);
        $services->ajukanPerpanjangan($pinjaman->fresh(), 'Sekali lagi.');
    }

    public function test_pinjaman_terlambat_tidak_dapat_diperpanjang(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $pinjaman->forceFill(['jatuh_tempo' => now()->subDays(3)])->save();

        $this->assertTrue($pinjaman->fresh()->terlambat());
        $this->assertSame(3, $pinjaman->fresh()->hariTerlambat());
        $this->assertFalse($pinjaman->fresh()->bolehDiperpanjang());

        $this->expectException(ValidationException::class);
        $services->ajukanPerpanjangan($pinjaman->fresh(), 'Lupa mengembalikan.');
    }

    /* ===================== Pengingat ===================== */

    public function test_pengingat_hari_sebelum_jatuh_tempo_terkirim_sekali(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $pinjaman->forceFill(['jatuh_tempo' => now()->addDay()->setTime(16, 0)])->save();

        $this->assertSame(1, $services->kirimPengingatH1());

        // Dijalankan lagi di hari yang sama → tidak mengirim ulang.
        $this->assertSame(0, $services->kirimPengingatH1());

        Notification::assertSentOnDemand(
            KabarPinjaman::class,
            fn (KabarPinjaman $n): bool => $n->keadaan === KabarPinjaman::PENGINGAT_H1,
        );
    }

    public function test_pengingat_terlambat_menyebut_tanpa_denda(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');
        $pinjaman->forceFill(['jatuh_tempo' => now()->subDays(2)])->save();

        $this->assertSame(1, $services->kirimPengingatTerlambat());

        Notification::assertSentOnDemand(
            KabarPinjaman::class,
            function (KabarPinjaman $n, array $saluran, object $notifiable): bool {
                $isi = (string) $n->toMail($notifiable)->render();

                return $n->keadaan === KabarPinjaman::TERLAMBAT
                    && str_contains($isi, 'tidak menerapkan denda');
            },
        );
    }

    /* ===================== 4. Pinjam dari dashboard ===================== */

    public function test_kader_dapat_meminjam_dari_area_anggota(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota();

        $this->actingAs($kader->user)
            ->post('/pustaka/pinjam', ['book_id' => $buku->id])
            ->assertSessionHas('sukses');

        $pinjaman = Loan::query()->firstOrFail();

        $this->assertSame($kader->id, $pinjaman->member_id);
        $this->assertSame(Loan::STATUS_DIAJUKAN, $pinjaman->status);
        $this->assertSame(Loan::JENIS_INTERNAL, $pinjaman->jenis);
    }

    public function test_anggota_hanya_melihat_pinjamannya_sendiri(): void
    {
        $buku = $this->buku(2);
        $kader = $this->anggota('Kader Satu');
        $lain = $this->anggota('Kader Dua');
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $milikLain = $services->ajukanBuku($buku, [], $lain);
        $services->setujui($milikLain, $petugas);
        $services->serahkan($milikLain->fresh(), $petugas, 'baik');

        $milikSendiri = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($milikSendiri, $petugas);

        $this->actingAs($kader->user)
            ->get('/pustaka')
            ->assertOk()
            ->assertSee($milikSendiri->kode_pinjam, false)
            ->assertDontSee($milikLain->kode_pinjam, false);
    }

    public function test_anggota_dapat_mengajukan_perpanjangan_dari_area_anggota(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');

        $this->actingAs($kader->user)
            ->post('/pustaka/'.$pinjaman->id.'/perpanjang', ['alasan' => 'Belum selesai dibaca.'])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('loan_extensions', [
            'loan_id' => $pinjaman->id,
            'status' => 'diajukan',
        ]);
    }

    public function test_anggota_tidak_dapat_mengajukan_pinjaman_untuk_orang_lain(): void
    {
        $buku = $this->buku(1);
        $kader = $this->anggota('Kader Satu');
        $lain = $this->anggota('Kader Dua');

        // Percobaan menitipkan id anggota lain diabaikan — pinjaman tetap
        // tercatat atas nama pengguna yang sedang masuk.
        $this->actingAs($kader->user)
            ->post('/pustaka/pinjam', ['book_id' => $buku->id, 'member_id' => $lain->id])
            ->assertSessionHas('sukses');

        $this->assertSame($kader->id, Loan::query()->firstOrFail()->member_id);
    }

    /* ===================== Regresi: opsi kondisi di panel ===================== */

    /**
     * Panel pernah menawarkan BookCopy::STATUS (Tersedia/Sedang Dipinjam) sebagai
     * pilihan KONDISI barang. Semua pilihan itu ditolak validasi, sehingga serah
     * terima dan pengembalian tidak pernah bisa diselesaikan lewat panel.
     */
    public function test_panel_peminjaman_menawarkan_kondisi_barang_bukan_status_eksemplar(): void
    {
        $petugas = $this->pengurus();

        $this->actingAs($petugas)
            ->get('/panel/peminjaman')
            ->assertOk()
            ->assertInertia(fn (Assert $halaman) => $halaman
                ->where('kondisiKeluar.baik', 'Baik')
                ->where('kondisiKeluar.rusak_ringan', 'Rusak Ringan')
                ->where('kondisiKeluar.rusak_berat', 'Rusak Berat')
                ->where('kondisiMasuk.hilang', 'Hilang')
                // Status eksemplar tidak boleh ikut ditawarkan sebagai kondisi.
                ->missing('kondisiKeluar.tersedia')
                ->missing('kondisiKeluar.dipinjam')
                // Barang tidak mungkin hilang saat masih di tangan petugas.
                ->missing('kondisiKeluar.hilang'));
    }

    public function test_serah_terima_menolak_status_eksemplar_dan_menerima_kondisi_barang(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $kader = $this->anggota();
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        $pinjaman = $services->ajukanBuku($buku, [], $kader);
        $services->setujui($pinjaman, $petugas);

        $this->actingAs($petugas)
            ->post("/panel/peminjaman/{$pinjaman->id}/serahkan", ['kondisi_keluar' => 'tersedia'])
            ->assertSessionHasErrors('kondisi_keluar');

        $this->assertSame(Loan::STATUS_DISETUJUI, $pinjaman->fresh()->status, 'Kondisi tidak sah tidak boleh mengubah status.');

        $this->actingAs($petugas)
            ->post("/panel/peminjaman/{$pinjaman->id}/serahkan", ['kondisi_keluar' => Loan::KONDISI_BAIK])
            ->assertSessionHasNoErrors();

        $this->assertSame(Loan::STATUS_DIPINJAM, $pinjaman->fresh()->status);
    }

    /**
     * Angka di kepala katalog, filter "hanya yang tersedia", dan label pada
     * kartu harus memakai ukuran yang sama — kalau tidak, katalog menjanjikan
     * judul yang pasti ditolak saat diajukan.
     */
    public function test_katalog_publik_menghitung_eksemplar_yang_ditahan_sebagai_tidak_tersedia(): void
    {
        Notification::fake();

        $buku = $this->buku(1);
        $petugas = $this->pengurus();
        $services = app(Peminjaman::class);

        // Sebelum ada antrian, judulnya dihitung tersedia.
        $this->get('/perpustakaan')
            ->assertOk()
            ->assertSee('1 judul siap dipinjam');

        $peminjam = $this->anggota('Peminjam Pertama');
        $pengantre = $this->anggota('Pengantre');

        $pinjaman = $services->ajukanBuku($buku, [], $peminjam);
        $services->setujui($pinjaman, $petugas);
        $services->serahkan($pinjaman->fresh(), $petugas, 'baik');
        $services->antri($buku, $pengantre);
        $services->kembalikan($pinjaman->fresh(), $petugas, 'baik');

        // Eksemplar sudah kembali ke rak, tetapi ditahan untuk pengantre.
        $this->assertSame(1, $buku->fresh()->eksemplarTersedia());
        $this->assertSame(0, $buku->fresh()->eksemplarBebas());

        $this->get('/perpustakaan')
            ->assertOk()
            ->assertSee('0 judul siap dipinjam')
            ->assertSee('Semua eksemplar dipinjam');

        // Filter "hanya yang tersedia" tidak boleh meloloskannya juga.
        $this->get('/perpustakaan?tersedia=1')
            ->assertOk()
            ->assertDontSee($buku->judulTeks());
    }

    /* ===================== Akses panel ===================== */
    public function test_panel_inventaris_dan_perpustakaan_terbuka_bagi_pengurus(): void
    {
        $sekretaris = $this->pengurus();

        foreach ([
            '/panel/inventaris',
            '/panel/inventaris/kategori',
            '/panel/perpustakaan',
            '/panel/peminjaman',
        ] as $tautan) {
            $this->actingAs($sekretaris)->get($tautan)->assertOk();
        }
    }

    public function test_data_perpustakaan_tertutup_bagi_kader_biasa(): void
    {
        $kader = $this->anggota();

        $this->actingAs($kader->user)->get('/panel/peminjaman')->assertForbidden();
        $this->actingAs($kader->user)->get('/panel/inventaris')->assertForbidden();
    }
}
