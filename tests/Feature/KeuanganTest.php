<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Donation;
use App\Models\DueCategory;
use App\Models\DueInvoice;
use App\Models\DuePayment;
use App\Models\Event;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Keuangan\PengingatIuran;
use App\Services\Hibah;
use App\Services\Iuran;
use App\Services\Kas;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase 7 — Keuangan.
 *
 * EMPAT JANJI YANG DIJAGA DI SINI:
 *  1. Saldo tidak pernah berubah tanpa jejak.
 *  2. Transaksi void mengembalikan saldo dengan benar.
 *  3. Tidak ada hapus — hanya void beralasan.
 *  4. Role lain sama sekali tidak bisa membuka halaman keuangan.
 *
 * Ditambah: hibah alumni tercatat benar — dana ke kas, barang ke stok
 * inventaris, jasa ke kegiatan.
 */
class KeuanganTest extends TestCase
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

    /* ===================== Helper ===================== */

    private function bendahara(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('bendahara');

        return $user;
    }

    private function pengurus(string $peran = 'konten_manager'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    private function anggota(string $nama = 'Siti Aminah', string $jalur = Member::JALUR_KADER, string $status = Member::STATUS_AKTIF): Member
    {
        $user = User::factory()->create([
            'name' => $nama,
            'email' => Str::slug($nama).'@contoh.test',
            'email_verified_at' => now(),
        ]);

        $member = new Member;
        $member->user_id = $user->id;
        $member->nama_lengkap = $nama;
        $member->status = $status;
        $member->jalur = $jalur;
        $member->save();

        return $member;
    }

    private function akun(string $nama = 'Kas Utama', int $saldoAwal = 1000000, array $ganti = []): FinanceAccount
    {
        $akun = new FinanceAccount;
        $akun->kode = $ganti['kode'] ?? 'KAS-'.str_pad((string) (FinanceAccount::query()->count() + 1), 2, '0', STR_PAD_LEFT);
        $akun->jenis = $ganti['jenis'] ?? FinanceAccount::JENIS_UTAMA;
        $akun->saldo_awal = $saldoAwal;
        $akun->saldo_berjalan = $saldoAwal;
        $akun->aktif = true;
        // Translasi diisi SEBELUM save (kolom JSON NOT NULL).
        $akun->setTranslations('nama', ['id' => $nama]);
        $akun->save();

        return $akun;
    }

    private function kategori(string $nama, string $jenis = FinanceCategory::JENIS_KELUAR, array $ganti = []): FinanceCategory
    {
        $kategori = new FinanceCategory;
        $kategori->kode = $ganti['kode'] ?? Str::upper(Str::slug($nama, '_'));
        $kategori->jenis = $jenis;
        $kategori->parent_id = $ganti['parent_id'] ?? null;
        $kategori->aktif = true;
        $kategori->setTranslations('nama', ['id' => $nama]);
        $kategori->save();

        return $kategori;
    }

    private function kategoriIuran(string $nama = 'Iuran Anggota Aktif', array $ganti = []): DueCategory
    {
        $kategori = new DueCategory;
        $kategori->kode = $ganti['kode'] ?? Str::upper(Str::slug($nama, '_'));
        $kategori->target_audiens = $ganti['target_audiens'] ?? DueCategory::TARGET_KADER_AKTIF;
        $kategori->nominal = $ganti['nominal'] ?? 15000;
        $kategori->periode = $ganti['periode'] ?? DueCategory::PERIODE_BULANAN;
        $kategori->aktif = true;
        $kategori->setTranslations('nama', ['id' => $nama]);
        $kategori->save();

        return $kategori;
    }

    private function tagihan(Member $anggota, int $nominal = 15000, string $periode = '2026-09'): DueInvoice
    {
        $kategori = $this->kategoriIuran('Iuran '.$periode, ['nominal' => $nominal, 'kode' => 'IURAN_'.Str::slug($periode, '_')]);

        $tagihan = new DueInvoice;
        $tagihan->due_category_id = $kategori->id;
        $tagihan->member_id = $anggota->id;
        $tagihan->periode_label = $periode;
        $tagihan->nominal = $nominal;
        $tagihan->status = DueInvoice::STATUS_BELUM;
        $tagihan->save();

        return $tagihan;
    }

    /* ===================== 1. Saldo & jejak ===================== */

    public function test_mencatat_transaksi_tidak_mengubah_saldo_sebelum_dikonfirmasi(): void
    {
        $akun = $this->akun(saldoAwal: 500000);
        $bendahara = $this->bendahara();

        $transaksi = app(Kas::class)->catat([
            'account_id' => $akun->id,
            'tanggal' => now()->toDateString(),
            'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 90000,
            'keterangan' => 'Donasi alumni',
        ], $bendahara);

        $this->assertSame(FinanceTransaction::STATUS_DRAFT, $transaksi->status);
        $this->assertSame(500000, $akun->fresh()->saldo_berjalan, 'Draft tidak boleh menyentuh saldo.');
        $this->assertNull($transaksi->saldo_sebelum);
    }

    public function test_konfirmasi_mengubah_saldo_dan_mencatat_jejak_sebelum_sesudah(): void
    {
        $akun = $this->akun(saldoAwal: 500000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id,
            'tanggal' => now()->toDateString(),
            'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 90000,
            'keterangan' => 'Donasi alumni',
        ], $bendahara);

        $kas->konfirmasi($transaksi, $bendahara);

        $transaksi->refresh();

        $this->assertSame(FinanceTransaction::STATUS_TERKONFIRMASI, $transaksi->status);
        $this->assertSame(500000, $transaksi->saldo_sebelum);
        $this->assertSame(590000, $transaksi->saldo_sesudah);
        $this->assertSame(590000, $akun->fresh()->saldo_berjalan);
        $this->assertSame($bendahara->id, $transaksi->dikonfirmasi_oleh);
        $this->assertNotNull($transaksi->dikonfirmasi_pada);
    }

    public function test_saldo_berjalan_selalu_sama_dengan_buku_kas(): void
    {
        $akun = $this->akun(saldoAwal: 1000000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $masuk = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 90000,
            'keterangan' => 'Iuran',
        ], $bendahara);
        $kas->konfirmasi($masuk, $bendahara);

        $keluar = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 40000,
            'keterangan' => 'Beli ATK',
        ], $bendahara);
        $kas->konfirmasi($keluar, $bendahara);

        $akun->refresh();

        $this->assertSame(1050000, $akun->saldo_berjalan);
        $this->assertTrue($kas->saldoSehat($akun), 'Saldo tersimpan harus sama dengan hitungan buku kas.');
    }

    public function test_saldo_tidak_pernah_bisa_menjadi_negatif(): void
    {
        $akun = $this->akun(saldoAwal: 50000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 200000,
            'keterangan' => 'Belanja melebihi saldo',
            'bukti_media_id' => 1,
        ], $bendahara);

        $this->expectException(ValidationException::class);
        $kas->konfirmasi($transaksi, $bendahara);
    }

    public function test_nominal_besar_wajib_disertai_bukti(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();

        $this->expectException(ValidationException::class);

        app(Kas::class)->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => FinanceTransaction::BATAS_WAJIB_BUKTI + 1,
            'keterangan' => 'Belanja besar tanpa nota',
        ], $bendahara);
    }

    public function test_kategori_penerimaan_tidak_boleh_dipakai_untuk_pengeluaran(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();
        $kategoriMasuk = $this->kategori('Sumbangan', FinanceCategory::JENIS_MASUK);

        $this->expectException(ValidationException::class);

        app(Kas::class)->catat([
            'account_id' => $akun->id,
            'category_id' => $kategoriMasuk->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 50000,
            'keterangan' => 'Salah kategori',
        ], $bendahara);
    }

    public function test_nomor_voucher_berurutan_per_tahun(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $satu = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 10000, 'keterangan' => 'A',
        ], $bendahara);

        $dua = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 10000, 'keterangan' => 'B',
        ], $bendahara);

        $tahun = now()->year;

        $this->assertSame('VCH-'.$tahun.'-0001', $satu->nomor_voucher);
        $this->assertSame('VCH-'.$tahun.'-0002', $dua->nomor_voucher);
    }

    /* ===================== 2. Void ===================== */

    public function test_void_mengembalikan_saldo_dan_menyimpan_alasan(): void
    {
        $akun = $this->akun(saldoAwal: 500000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 200000,
            'keterangan' => 'Beli sound system',
            'bukti_media_id' => 1,
        ], $bendahara);

        $kas->konfirmasi($transaksi, $bendahara);
        $this->assertSame(300000, $akun->fresh()->saldo_berjalan);

        $kas->void($transaksi->fresh(), $bendahara, 'Salah input nominal');

        $transaksi->refresh();

        $this->assertSame(FinanceTransaction::STATUS_VOID, $transaksi->status);
        $this->assertSame(500000, $akun->fresh()->saldo_berjalan, 'Saldo harus kembali seperti sebelum transaksi.');
        $this->assertSame($bendahara->id, $transaksi->void_oleh);
        $this->assertSame('Salah input nominal', $transaksi->void_alasan);

        // Angka historisnya TIDAK ditimpa — itu bukti transaksinya pernah terjadi.
        $this->assertSame(500000, $transaksi->saldo_sebelum);
        $this->assertSame(300000, $transaksi->saldo_sesudah);

        $this->assertTrue($kas->saldoSehat($akun->fresh()));
    }

    public function test_void_wajib_beralasan(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 50000, 'keterangan' => 'X',
        ], $bendahara);
        $kas->konfirmasi($transaksi, $bendahara);

        $this->expectException(ValidationException::class);
        $kas->void($transaksi->fresh(), $bendahara, '   ');
    }

    public function test_void_dua_kali_ditolak(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 50000, 'keterangan' => 'X',
        ], $bendahara);
        $kas->konfirmasi($transaksi, $bendahara);
        $kas->void($transaksi->fresh(), $bendahara, 'Salah');

        $this->expectException(ValidationException::class);
        $kas->void($transaksi->fresh(), $bendahara, 'Salah lagi');
    }

    public function test_void_transaksi_draft_tidak_mengubah_saldo(): void
    {
        $akun = $this->akun(saldoAwal: 400000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 100000, 'keterangan' => 'Draft',
        ], $bendahara);

        $kas->void($transaksi, $bendahara, 'Batal sebelum dikonfirmasi');

        $this->assertSame(FinanceTransaction::STATUS_VOID, $transaksi->fresh()->status);
        $this->assertSame(400000, $akun->fresh()->saldo_berjalan);
    }

    public function test_transaksi_tidak_pernah_dihapus_dari_basis_data(): void
    {
        $akun = $this->akun();
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $transaksi = $kas->catat([
            'account_id' => $akun->id, 'jenis' => FinanceTransaction::JENIS_MASUK,
            'jumlah' => 75000, 'keterangan' => 'Y',
        ], $bendahara);
        $kas->konfirmasi($transaksi, $bendahara);
        $kas->void($transaksi->fresh(), $bendahara, 'Salah');

        $this->assertDatabaseHas('finance_transactions', [
            'id' => $transaksi->id,
            'status' => FinanceTransaction::STATUS_VOID,
        ]);
    }

    /* ===================== 3. Iuran ===================== */

    public function test_terbitkan_tagihan_untuk_semua_kader_aktif(): void
    {
        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);

        $this->anggota('Kader Satu');
        $this->anggota('Kader Dua');
        $this->anggota('Alumni Satu', Member::JALUR_ALUMNI, Member::STATUS_ALUMNI);

        $jumlah = app(Iuran::class)->terbitkan($kategori, '2026-09', null, $this->bendahara());

        $this->assertSame(2, $jumlah, 'Hanya kader aktif yang tertagih.');
        $this->assertSame(2, DueInvoice::query()->where('periode_label', '2026-09')->count());
    }

    public function test_terbitkan_dua_kali_tidak_menggandakan_tagihan(): void
    {
        $kategori = $this->kategoriIuran();
        $this->anggota('Kader Satu');
        $iuran = app(Iuran::class);
        $bendahara = $this->bendahara();

        $pertama = $iuran->terbitkan($kategori, '2026-09', null, $bendahara);
        $kedua = $iuran->terbitkan($kategori, '2026-09', null, $bendahara);

        $this->assertSame(1, $pertama);
        $this->assertSame(0, $kedua, 'Periode yang sama tidak boleh diterbitkan dua kali.');
        $this->assertSame(1, DueInvoice::query()->count());
    }

    public function test_pembayaran_tunai_langsung_lunas_dan_membentuk_kas_masuk(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        app(Iuran::class)->catatTunai($tagihan, 25000, $bendahara, ['account_id' => $akun->id]);

        $tagihan->refresh();

        $this->assertSame(DueInvoice::STATUS_LUNAS, $tagihan->status);
        $this->assertSame(25000, $akun->fresh()->saldo_berjalan);

        $transaksi = FinanceTransaction::query()->firstOrFail();
        $this->assertTrue($transaksi->terkonfirmasi());
        $this->assertSame(FinanceTransaction::SUMBER_IURAN, $transaksi->sumber);
        $this->assertNotNull($transaksi->due_payment_id);
    }

    public function test_transfer_belum_lunas_sebelum_diverifikasi(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        $pembayaran = app(Iuran::class)->ajukanTransfer($tagihan, 25000, $anggota);

        $this->assertSame(DuePayment::STATUS_MENUNGGU, $pembayaran->status);
        $this->assertSame(DueInvoice::STATUS_MENUNGGU, $tagihan->fresh()->status);
        $this->assertSame(0, $akun->fresh()->saldo_berjalan, 'Transfer yang belum diverifikasi tidak boleh menambah saldo.');
        $this->assertSame(0, FinanceTransaction::query()->count());

        app(Iuran::class)->verifikasi($pembayaran, $bendahara, ['account_id' => $akun->id]);

        $this->assertSame(DueInvoice::STATUS_LUNAS, $tagihan->fresh()->status);
        $this->assertSame(25000, $akun->fresh()->saldo_berjalan);
        $this->assertSame(1, FinanceTransaction::query()->count(), 'Satu pembayaran = satu transaksi kas.');
    }

    public function test_bukti_transfer_ditolak_membuat_tagihan_perlu_unggah_ulang(): void
    {
        $bendahara = $this->bendahara();
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        $pembayaran = app(Iuran::class)->ajukanTransfer($tagihan, 25000, $anggota);
        app(Iuran::class)->tolak($pembayaran, $bendahara, 'Bukti tidak terbaca');

        $this->assertSame(DuePayment::STATUS_DITOLAK, $pembayaran->fresh()->status);
        $this->assertSame(DueInvoice::STATUS_DITOLAK, $tagihan->fresh()->status);
        $this->assertSame(0, FinanceTransaction::query()->count());
    }

    public function test_pembebasan_tagihan_wajib_beralasan(): void
    {
        $t = $this->tagihan($this->anggota(), 25000);
        $iuran = app(Iuran::class);
        $bendahara = $this->bendahara();

        try {
            $iuran->bebaskan($t, $bendahara, '  ');
            $this->fail('Pembebasan tanpa alasan seharusnya ditolak.');
        } catch (ValidationException) {
            // benar
        }

        $iuran->bebaskan($t, $bendahara, 'Kader sedang di luar kota');

        $this->assertSame(DueInvoice::STATUS_DIBEBASKAN, $t->fresh()->status);
        $this->assertSame('Kader sedang di luar kota', $t->fresh()->dibebaskan_alasan);
    }

    public function test_rekap_iuran_menghitung_sudah_dan_belum_bayar(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $lunas = $this->anggota('Kader Lunas');
        $belum = $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $bendahara);

        $tagihanLunas = DueInvoice::query()->where('member_id', $lunas->id)->firstOrFail();
        $iuran->catatTunai($tagihanLunas, 20000, $bendahara, ['account_id' => $akun->id]);

        $rekap = $iuran->rekap($kategori, '2026-09');

        $this->assertSame(2, $rekap['tagihan']->count());
        $this->assertSame(1, $rekap['lunas']);
        $this->assertSame(1, $rekap['belum']);
        $this->assertSame(20000, $rekap['nominal_terkumpul']);
        $this->assertSame(40000, $rekap['nominal_target']);
    }

    /* ===================== 3b. Pengingat iuran ===================== */

    public function test_pengingat_iuran_hanya_untuk_yang_belum_bayar(): void
    {
        Notification::fake();

        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $lunas = $this->anggota('Kader Lunas');
        $menunggu = $this->anggota('Kader Menunggu');
        $belum = $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $bendahara);

        // Sudah lunas lewat tunai.
        $iuran->catatTunai(
            DueInvoice::query()->where('member_id', $lunas->id)->firstOrFail(),
            20000,
            $bendahara,
            ['account_id' => $akun->id],
        );

        // Sudah mengunggah bukti, tinggal diperiksa Bendahara.
        $iuran->ajukanTransfer(
            DueInvoice::query()->where('member_id', $menunggu->id)->firstOrFail(),
            20000,
            $menunggu,
        );

        $hasil = $iuran->ingatkan($kategori, '2026-09', $bendahara);

        $this->assertSame(2, $hasil['total']);
        $this->assertSame(1, $hasil['terkirim']);
        $this->assertSame(1, $hasil['menunggu_verifikasi']);
        $this->assertSame(0, $hasil['baru_diingatkan']);

        Notification::assertSentTo($belum->user, PengingatIuran::class);
        Notification::assertNotSentTo($lunas->user, PengingatIuran::class);
        Notification::assertNotSentTo($menunggu->user, PengingatIuran::class);

        $terkirim = DueInvoice::query()->where('member_id', $belum->id)->firstOrFail();

        $this->assertSame(1, $terkirim->pengingat_terkirim);
        $this->assertNotNull($terkirim->pengingat_terakhir_pada);
    }

    public function test_pengingat_tidak_digandakan_dalam_jeda(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $kader = $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $bendahara);

        $pertama = $iuran->ingatkan($kategori, '2026-09', $bendahara);
        $this->assertSame(1, $pertama['terkirim']);

        // Klik kedua — inilah yang bikin anggota terlewat marah kalau tidak dicegah.
        Notification::fake();

        $kedua = $iuran->ingatkan($kategori, '2026-09', $bendahara);

        $this->assertSame(0, $kedua['terkirim']);
        $this->assertSame(1, $kedua['baru_diingatkan']);
        Notification::assertNothingSent();

        // Setelah jeda lewat, boleh diingatkan lagi.
        $this->travel(Iuran::JEDA_PENGINGAT_JAM + 1)->hours();

        $ketiga = $iuran->ingatkan($kategori, '2026-09', $bendahara);

        $this->assertSame(1, $ketiga['terkirim']);

        $tagihan = DueInvoice::query()->where('member_id', $kader->id)->firstOrFail();

        $this->assertSame(2, $tagihan->pengingat_terkirim);
    }

    public function test_pengingat_menyebut_nominal_dan_tenggat_tagihan(): void
    {
        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 25000]);
        $kader = $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', '2026-09-30', $bendahara);

        $tagihan = DueInvoice::query()->where('member_id', $kader->id)->firstOrFail();

        $surat = (new PengingatIuran($tagihan))->toMail($kader->user);

        $isi = implode(' ', array_map(
            fn ($baris): string => is_string($baris) ? $baris : json_encode($baris),
            $surat->introLines,
        ));

        $this->assertStringContainsString('Rp25.000', $isi);
        $this->assertStringContainsString('2026-09', $surat->subject);
        // Rayon ini tidak menerapkan denda — surat pengingat tidak boleh menyebutnya.
        $this->assertStringNotContainsString('denda', strtolower($isi));
    }

    public function test_pengingat_dapat_dikirim_dari_panel_dan_melaporkan_hasilnya(): void
    {
        Notification::fake();

        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $bendahara);

        $respons = $this->actingAs($bendahara)
            ->post("/panel/keuangan/iuran/kategori/{$kategori->id}/ingatkan", [
                'periode_label' => '2026-09',
            ]);

        $respons->assertRedirect();
        $this->assertStringContainsString('1 pengingat terkirim', (string) session('sukses'));

        Notification::assertSentTimes(PengingatIuran::class, 1);
    }

    public function test_pengingat_tidak_dapat_dikirim_oleh_yang_tak_berwenang(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $this->bendahara());

        $this->actingAs($this->pengurus('konten_manager'))
            ->post("/panel/keuangan/iuran/kategori/{$kategori->id}/ingatkan", [
                'periode_label' => '2026-09',
            ])
            ->assertForbidden();
    }

    public function test_pesan_pengingat_membedakan_dilewati_dan_terkirim(): void
    {
        Notification::fake();

        $bendahara = $this->bendahara();
        $iuran = app(Iuran::class);

        $kategori = $this->kategoriIuran('Iuran Bulanan', ['nominal' => 20000]);
        $this->anggota('Kader Belum');

        $iuran->terbitkan($kategori, '2026-09', null, $bendahara);
        $iuran->ingatkan($kategori, '2026-09', $bendahara);

        $respons = $this->actingAs($bendahara)
            ->post("/panel/keuangan/iuran/kategori/{$kategori->id}/ingatkan", [
                'periode_label' => '2026-09',
            ]);

        $pesan = (string) session('sukses');

        $respons->assertRedirect();
        $this->assertStringContainsString('0 pengingat terkirim', $pesan);
        $this->assertStringContainsString('24 jam terakhir', $pesan);
    }

    /* ===================== 4. Anggaran ===================== */
    public function test_realisasi_anggaran_dihitung_dari_transaksi_terkonfirmasi(): void
    {
        $akun = $this->akun(saldoAwal: 1000000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $anggaran = new Budget;
        $anggaran->jenis = Budget::JENIS_KELUAR;
        $anggaran->jumlah_direncanakan = 500000;
        $anggaran->aktif = true;
        $anggaran->setTranslations('nama', ['id' => 'Belanja Kegiatan Mapaba']);
        $anggaran->save();

        $transaksi = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 200000,
            'keterangan' => 'Konsumsi Mapaba',
            'budget_id' => $anggaran->id,
            'bukti_media_id' => 1,
        ], $bendahara);

        // Draft belum dihitung sebagai realisasi.
        $this->assertSame(0, $anggaran->fresh()->realisasi());

        $kas->konfirmasi($transaksi, $bendahara);

        $anggaran->refresh();

        $this->assertSame(200000, $anggaran->realisasi());
        $this->assertSame(300000, $anggaran->sisa());
        $this->assertSame(40, $anggaran->persenRealisasi());
        $this->assertFalse($anggaran->melebihiRencana());
    }

    public function test_void_mengurangi_realisasi_anggaran(): void
    {
        $akun = $this->akun(saldoAwal: 1000000);
        $bendahara = $this->bendahara();
        $kas = app(Kas::class);

        $anggaran = new Budget;
        $anggaran->jenis = Budget::JENIS_KELUAR;
        $anggaran->jumlah_direncanakan = 500000;
        $anggaran->aktif = true;
        $anggaran->setTranslations('nama', ['id' => 'Belanja ATK']);
        $anggaran->save();

        $transaksi = $kas->catat([
            'account_id' => $akun->id,
            'jenis' => FinanceTransaction::JENIS_KELUAR,
            'jumlah' => 150000,
            'keterangan' => 'ATK',
            'budget_id' => $anggaran->id,
            'bukti_media_id' => 1,
        ], $bendahara);
        $kas->konfirmasi($transaksi, $bendahara);

        $this->assertSame(150000, $anggaran->fresh()->realisasi());

        $kas->void($transaksi->fresh(), $bendahara, 'Nota batal');

        $this->assertSame(0, $anggaran->fresh()->realisasi(), 'Realisasi ikut turun saat transaksinya di-void.');
    }

    /* ===================== 5. Hibah ===================== */

    public function test_hibah_dana_membentuk_kas_masuk_bertanda_hibah(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $donasi = $hibah->ajukan([
            'jenis' => Donation::JENIS_DANA,
            'judul' => 'Dukungan Mapaba',
            'nama_pemberi' => 'Alumni 2015',
            'estimasi_nilai' => 1000000,
            'deskripsi' => 'Untuk konsumsi peserta',
            'bukti_media_id' => 1,
        ], $this->anggota('Alumni Nun', Member::JALUR_ALUMNI, Member::STATUS_ALUMNI));

        $this->assertSame(Donation::STATUS_DIAJUKAN, $donasi->status);
        $this->assertSame(0, $akun->fresh()->saldo_berjalan);

        $hibah->setujui($donasi, $bendahara);
        $hibah->terima($donasi->fresh(), $bendahara, [
            'account_id' => $akun->id,
            'nilai_diterima' => 1000000,
        ]);

        $donasi->refresh();

        $this->assertSame(Donation::STATUS_DITERIMA, $donasi->status);
        $this->assertNotNull($donasi->transaction_id);
        $this->assertSame(1000000, $akun->fresh()->saldo_berjalan);

        $transaksi = FinanceTransaction::query()->findOrFail($donasi->transaction_id);
        $this->assertSame(FinanceTransaction::SUMBER_HIBAH, $transaksi->sumber);
        $this->assertTrue($transaksi->terkonfirmasi());
    }

    public function test_hibah_barang_menambah_stok_inventaris(): void
    {
        $bendahara = $this->bendahara();

        $aset = new InventoryItem;
        $aset->kode = 'INV-HIB-01';
        $aset->satuan = 'buah';
        $aset->jumlah = 2;
        $aset->kondisi = InventoryItem::KONDISI_BAIK;
        $aset->aktif = true;
        $aset->setTranslations('nama', ['id' => 'Tenda Hibah']);
        $aset->save();

        $hibah = app(Hibah::class);

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_BARANG,
            'judul' => 'Dua tenda',
            'nama_pemberi' => 'Alumni 2010',
            'estimasi_nilai' => 1500000,
        ], $bendahara);

        $hibah->terima($donasi, $bendahara, [
            'inventory_item_id' => $aset->id,
            'jumlah' => 2,
            'kondisi_barang' => InventoryItem::KONDISI_BAIK,
        ]);

        $donasi->refresh();

        $this->assertSame(Donation::STATUS_DITERIMA, $donasi->status);
        $this->assertNotNull($donasi->inventory_movement_id);
        $this->assertSame(4, $aset->fresh()->jumlah, 'Stok aset harus bertambah dari mutasi hibah.');

        // Tidak menyentuh kas sama sekali.
        $this->assertNull($donasi->transaction_id);
        $this->assertSame(0, FinanceTransaction::query()->count());
    }

    public function test_hibah_jasa_tidak_menyentuh_kas_maupun_stok(): void
    {
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $kegiatan = new Event;
        $kegiatan->jenis = 'pkd';
        $kegiatan->aktif = true;
        $kegiatan->setTranslations('judul', ['id' => 'PKD 2026']);
        $kegiatan->setTranslations('slug', ['id' => 'pkd-2026']);
        $kegiatan->save();

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_JASA,
            'judul' => 'Kesediaan jadi pemateri',
            'nama_pemberi' => 'Alumni 2012',
            'estimasi_nilai' => 500000,
        ], $bendahara);

        $hibah->terima($donasi, $bendahara, ['event_id' => $kegiatan->id]);

        $donasi->refresh();

        $this->assertSame(Donation::STATUS_DITERIMA, $donasi->status);
        $this->assertSame($kegiatan->id, $donasi->event_id);
        $this->assertNull($donasi->transaction_id);
        $this->assertNull($donasi->inventory_movement_id);
        $this->assertSame(0, FinanceTransaction::query()->count());
    }

    public function test_hibah_anonim_menyembunyikan_nama_kecuali_bagi_superadmin(): void
    {
        $bendahara = $this->bendahara();
        $superadmin = User::factory()->create(['email_verified_at' => now()]);
        $superadmin->assignRole('superadmin');

        $donasi = app(Hibah::class)->catatLangsung([
            'jenis' => Donation::JENIS_DANA,
            'judul' => 'Sumbangan tanpa nama',
            'nama_pemberi' => 'Nama Asli',
            'estimasi_nilai' => 300000,
            'anonim' => true,
        ], $bendahara);

        $this->assertSame('Nama Asli', $donasi->namaPemberi($superadmin));
        $this->assertSame('Hamba Allah (anonim)', $donasi->namaPemberi($bendahara));
    }

    public function test_hibah_dana_tanpa_akun_kas_ditolak(): void
    {
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_DANA,
            'judul' => 'Dana',
            'nama_pemberi' => 'Alumni',
            'estimasi_nilai' => 100000,
        ], $bendahara);

        $this->expectException(ValidationException::class);

        $hibah->terima($donasi, $bendahara, ['nilai_diterima' => 100000]);
    }

    /**
     * Hibah besar mewajibkan bukti. Buktinya boleh ditempelkan petugas SAAT
     * menerima — kalau tidak, hibah di atas batas wajib bukti tidak akan pernah
     * bisa diterima lewat panel.
     */
    public function test_hibah_dana_besar_dapat_diterima_bila_bukti_dilampirkan_saat_penerimaan(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_DANA,
            'judul' => 'Dukungan besar',
            'nama_pemberi' => 'Alumni 2015',
            'estimasi_nilai' => 500000,
        ], $bendahara);

        // Tanpa bukti → ditolak oleh aturan bukti di layanan Kas.
        try {
            $hibah->terima($donasi, $bendahara, ['account_id' => $akun->id, 'nilai_diterima' => 500000]);
            $this->fail('Hibah besar tanpa bukti seharusnya ditolak.');
        } catch (ValidationException) {
            // benar
        }

        $this->assertSame(0, $akun->fresh()->saldo_berjalan);

        $hibah->terima($donasi->fresh(), $bendahara, [
            'account_id' => $akun->id,
            'nilai_diterima' => 500000,
            'bukti_media_id' => 7,
        ]);

        $donasi->refresh();

        $this->assertSame(Donation::STATUS_DITERIMA, $donasi->status);
        $this->assertSame(500000, $akun->fresh()->saldo_berjalan);

        $transaksi = FinanceTransaction::query()->findOrFail($donasi->transaction_id);
        $this->assertSame(7, $transaksi->bukti_media_id, 'Bukti dari penerimaan harus menempel pada transaksi kas.');
    }

    public function test_nomor_hibah_berurutan_per_tahun(): void
    {
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);
        $tahun = now()->year;

        $satu = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_JASA, 'judul' => 'A', 'nama_pemberi' => 'X', 'estimasi_nilai' => 0,
        ], $bendahara);

        $dua = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_JASA, 'judul' => 'B', 'nama_pemberi' => 'Y', 'estimasi_nilai' => 0,
        ], $bendahara);

        $this->assertSame('HIB-'.$tahun.'-0001', $satu->nomor_hibah);
        $this->assertSame('HIB-'.$tahun.'-0002', $dua->nomor_hibah);
    }

    public function test_hibah_tidak_dapat_diterima_dua_kali(): void
    {
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_JASA,
            'judul' => 'Jasa',
            'nama_pemberi' => 'Alumni',
            'estimasi_nilai' => 0,
        ], $bendahara);

        $hibah->terima($donasi, $bendahara);

        $this->expectException(ValidationException::class);
        $hibah->terima($donasi->fresh(), $bendahara);
    }

    public function test_hibah_ditolak_wajib_beralasan(): void
    {
        $bendahara = $this->bendahara();
        $hibah = app(Hibah::class);

        $donasi = $hibah->catatLangsung([
            'jenis' => Donation::JENIS_DANA,
            'judul' => 'Dana',
            'nama_pemberi' => 'Alumni',
            'estimasi_nilai' => 100000,
        ], $bendahara);

        $this->expectException(ValidationException::class);
        $hibah->tolak($donasi, $bendahara, '');
    }

    /* ===================== 6. Kategori bertingkat ===================== */

    public function test_kategori_bertingkat_memiliki_label_jalur(): void
    {
        $induk = $this->kategori('Belanja', FinanceCategory::JENIS_KELUAR);
        $anak = $this->kategori('ATK', FinanceCategory::JENIS_KELUAR, ['parent_id' => $induk->id]);

        $this->assertSame('Belanja › ATK', $anak->labelLengkap());
        $this->assertSame('Belanja', $induk->labelLengkap());
        $this->assertSame(1, $induk->anak()->count());
    }

    /* ===================== 7. Iuran dari sisi anggota ===================== */

    public function test_anggota_melihat_tagihannya_sendiri(): void
    {
        $saya = $this->anggota('Kader Saya');
        $lain = $this->anggota('Kader Lain');

        $this->tagihan($saya, 15000, '2026-09');
        $this->tagihan($lain, 15000, '2026-10');

        $this->actingAs($saya->user)
            ->get('/iuran')
            ->assertOk()
            ->assertInertia(fn (Assert $halaman) => $halaman
                ->component('Anggota/Iuran', false)
                ->has('tagihan', 1)
                ->where('tagihan.0.periode_label', '2026-09')
                ->where('ringkasan.belum_bayar', 1)
                ->where('ringkasan.tunggakan', 15000));
    }

    public function test_anggota_dapat_mengirim_bukti_transfer_dan_tagihannya_menunggu(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        $this->actingAs($anggota->user)
            ->post("/iuran/{$tagihan->id}/bukti", [
                'jumlah' => 25000,
                'catatan_pembayar' => 'Transfer dari rekening orang tua',
                // create(), bukan image(): container ini tidak punya ekstensi GD.
                'bukti' => UploadedFile::fake()->create('bukti.png', 100, 'image/png'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(DueInvoice::STATUS_MENUNGGU, $tagihan->fresh()->status);
        $this->assertSame(DuePayment::STATUS_MENUNGGU, DuePayment::query()->firstOrFail()->status);

        // Unggahan anggota BELUM menyentuh kas — hanya Bendahara yang boleh.
        $this->assertSame(0, $akun->fresh()->saldo_berjalan);
        $this->assertSame(0, FinanceTransaction::query()->count());
    }

    public function test_anggota_tidak_dapat_mengirim_bukti_untuk_tagihan_orang_lain(): void
    {
        $saya = $this->anggota('Kader Saya');
        $lain = $this->anggota('Kader Lain');
        $tagihanLain = $this->tagihan($lain, 25000);

        $this->actingAs($saya->user)
            ->post("/iuran/{$tagihanLain->id}/bukti", [
                'jumlah' => 25000,
                'bukti' => UploadedFile::fake()->create('bukti.png', 100, 'image/png'),
            ])
            ->assertSessionHas('galat');

        $this->assertSame(DueInvoice::STATUS_BELUM, $tagihanLain->fresh()->status);
        $this->assertSame(0, DuePayment::query()->count());
    }

    public function test_bukti_wajib_berupa_berkas_yang_diizinkan(): void
    {
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        $this->actingAs($anggota->user)
            ->post("/iuran/{$tagihan->id}/bukti", [
                'jumlah' => 25000,
                'bukti' => UploadedFile::fake()->create('virus.exe', 10),
            ])
            ->assertSessionHasErrors('bukti');
    }

    public function test_anggota_tidak_dapat_mengirim_bukti_untuk_tagihan_yang_sudah_lunas(): void
    {
        $akun = $this->akun(saldoAwal: 0);
        $bendahara = $this->bendahara();
        $anggota = $this->anggota();
        $tagihan = $this->tagihan($anggota, 25000);

        app(Iuran::class)->catatTunai($tagihan, 25000, $bendahara, ['account_id' => $akun->id]);

        $this->actingAs($anggota->user)
            ->post("/iuran/{$tagihan->id}/bukti", [
                'jumlah' => 25000,
                'bukti' => UploadedFile::fake()->create('bukti.png', 100, 'image/png'),
            ])
            ->assertSessionHas('galat');

        $this->assertSame(1, DuePayment::query()->count(), 'Tidak boleh ada pembayaran kedua.');
    }

    public function test_halaman_iuran_anggota_tertutup_bagi_pengurus_tanpa_data_anggota(): void
    {
        $this->actingAs($this->bendahara())
            ->get('/iuran')
            ->assertForbidden();
    }

    /* ===================== 8. Akses ===================== */
    public function test_halaman_keuangan_hanya_untuk_bendahara_dan_superadmin(): void
    {
        $bendahara = $this->bendahara();

        $superadmin = User::factory()->create(['email_verified_at' => now()]);
        $superadmin->assignRole('superadmin');

        foreach ([
            '/panel/keuangan',
            '/panel/keuangan/kategori',
            '/panel/keuangan/transaksi',
            '/panel/keuangan/iuran',
            '/panel/keuangan/anggaran',
            '/panel/keuangan/laporan',
            '/panel/keuangan/hibah',
        ] as $tautan) {
            $this->actingAs($bendahara)->get($tautan)->assertOk();
            $this->actingAs($superadmin)->get($tautan)->assertOk();
        }
    }

    public function test_role_lain_sama_sekali_tidak_bisa_membuka_keuangan(): void
    {
        $konten = $this->pengurus('konten_manager');
        $sekretaris = $this->pengurus('sekretaris');
        $kader = $this->anggota();

        foreach (['/panel/keuangan', '/panel/keuangan/transaksi', '/panel/keuangan/laporan', '/panel/keuangan/hibah'] as $tautan) {
            $this->actingAs($konten)->get($tautan)->assertForbidden();
            $this->actingAs($sekretaris)->get($tautan)->assertForbidden();
            $this->actingAs($kader->user)->get($tautan)->assertForbidden();
        }
    }
}
