<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Notifications\Event\KabarPendaftaran;
use App\Notifications\Keanggotaan\PengajuanDisetujui;
use App\Notifications\Keanggotaan\PengajuanDitolak;
use App\Notifications\Keanggotaan\PengajuanPerluPerbaikan;
use App\Notifications\Keanggotaan\StatusKeanggotaanBerubah;
use App\Notifications\Keuangan\PengingatIuran;
use App\Notifications\Pustaka\BukuSiapDiambil;
use App\Notifications\Pustaka\KabarPinjaman;
use App\Notifications\Redaksi\KabarArtikel;
use App\Services\Notifikasi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionClass;
use Tests\TestCase;

/**
 * Pusat notifikasi & laporan.
 *
 * DUA JANJI YANG DIUJI DI SINI:
 *
 * 1. Notifikasi dalam aplikasi TIDAK PERNAH bisa dimatikan, dan notifikasi
 *    keanggotaan tidak pernah bisa dimatikan emailnya — surat itulah yang
 *    memberi tahu seseorang bahwa ia sudah boleh masuk.
 *
 * 2. Halaman laporan TIDAK PERNAH menjadi pintu belakang menuju data yang di
 *    modul aslinya sengaja dibatasi.
 */
class NotifikasiDanLaporanTest extends TestCase
{
    use RefreshDatabase;

    /** Kelas notifikasi yang ada beserta kategorinya. */
    private const KELAS = [
        PengajuanDisetujui::class => Notifikasi::KEANGGOTAAN,
        PengajuanDitolak::class => Notifikasi::KEANGGOTAAN,
        PengajuanPerluPerbaikan::class => Notifikasi::KEANGGOTAAN,
        StatusKeanggotaanBerubah::class => Notifikasi::KEANGGOTAAN,
        BukuSiapDiambil::class => Notifikasi::PUSTAKA,
        KabarPinjaman::class => Notifikasi::PUSTAKA,
        PengingatIuran::class => Notifikasi::KEUANGAN,
        KabarPendaftaran::class => Notifikasi::EVENT,
        KabarArtikel::class => Notifikasi::REDAKSI,
    ];

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

    private function pengurus(string $peran = 'sekretaris'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    private function kader(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->buatAnggota($user);

        return $user;
    }

    /**
     * Satu pengguna hanya boleh punya SATU baris keanggotaan, dan NOMOR ANGGOTA
     * harus unik — dua anggota dalam satu uji dengan nomor yang sama akan
     * ditolak basis data.
     *
     * Relasinya dibaca lewat `member()` (kueri), BUKAN `$user->member`.
     * Membaca lewat properti meng-cache hasilnya pada objek itu, sehingga
     * pemeriksaan "sudah punya anggota?" berikutnya masih melihat null dan
     * mencoba menyisipkan baris kedua untuk user_id yang sama.
     */
    private function buatAnggota(User $user, ?string $nomor = null): Member
    {
        $ada = $user->member()->first();

        if ($ada !== null) {
            return $ada;
        }

        $anggota = new Member;
        $anggota->user_id = $user->id;
        $anggota->nomor_anggota = $nomor ?? 'RAAB-2026-'.str_pad((string) (Member::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        $anggota->nama_lengkap = 'Kader Uji';
        $anggota->status = Member::STATUS_AKTIF;
        $anggota->jalur = Member::JALUR_KADER;
        $anggota->save();

        $user->setRelation('member', $anggota);

        return $anggota;
    }

    /**
     * Notifikasi tanpa menjalankan konstruktornya.
     *
     * Dipakai untuk MEMERIKSA `via()` dan `kategori()` saja. Keduanya tidak
     * menyentuh properti apa pun — `kategori()` mengembalikan konstanta — jadi
     * cara ini sah dan menghindarkan uji ini dari keharusan menyiapkan
     * pinjaman, tagihan, dan artikel hanya untuk memeriksa kanal pengiriman.
     */
    private function notifikasiKosong(string $kelas): object
    {
        return (new ReflectionClass($kelas))->newInstanceWithoutConstructor();
    }

    /* ===================== Semua kelas memakai pusat notifikasi ===================== */

    public function test_semua_notifikasi_ikut_masuk_pusat_notifikasi(): void
    {
        foreach (self::KELAS as $kelas => $kategori) {
            $this->assertContains(
                MasukPusatNotifikasi::class,
                class_uses_recursive($kelas),
                $kelas.' tidak memakai trait pusat notifikasi.',
            );

            $this->assertSame($kategori, $this->notifikasiKosong($kelas)->kategori(), 'Kategori '.$kelas.' salah.');
        }
    }

    public function test_setiap_kelas_notifikasi_punya_kategori_yang_dikenal(): void
    {
        foreach (array_keys(self::KELAS) as $kelas) {
            $kategori = $this->notifikasiKosong($kelas)->kategori();

            $this->assertArrayHasKey($kategori, Notifikasi::KATEGORI, 'Kategori '.$kategori.' tidak punya label.');
        }
    }

    /* ===================== Kanal ===================== */

    public function test_notifikasi_selalu_masuk_aplikasi_dan_email_secara_bawaan(): void
    {
        $user = User::factory()->create();

        // Kolom preferensi masih kosong (null) — pengguna lama tidak boleh
        // kehilangan surat hanya karena ada kolom baru.
        $this->assertNull($user->preferensi_notifikasi);
        $this->assertSame(['database', 'mail'], Notifikasi::kanal($user, Notifikasi::PUSTAKA));
    }

    public function test_mematikan_email_suatu_kategori_tidak_mematikan_notifikasi_aplikasi(): void
    {
        $user = User::factory()->create();

        Notifikasi::simpanPreferensi($user, [Notifikasi::PUSTAKA => false]);

        $pinjaman = $this->notifikasiKosong(KabarPinjaman::class);

        $this->assertSame(['database'], $pinjaman->via($user->fresh()));
    }

    public function test_email_keanggotaan_tidak_pernah_bisa_dimatikan(): void
    {
        $user = User::factory()->create();

        Notifikasi::simpanPreferensi($user, [
            Notifikasi::KEANGGOTAAN => false,
            Notifikasi::PUSTAKA => false,
        ]);

        // Disimpan sebagai true, bukan sebagai false yang lalu diabaikan saat
        // pengiriman — supaya aturannya hanya hidup di satu tempat.
        $this->assertTrue($user->fresh()->preferensi_notifikasi[Notifikasi::KEANGGOTAAN]);
        $this->assertFalse($user->fresh()->preferensi_notifikasi[Notifikasi::PUSTAKA]);

        $disetujui = $this->notifikasiKosong(PengajuanDisetujui::class);

        $this->assertSame(['database', 'mail'], $disetujui->via($user->fresh()));
    }

    public function test_kategori_wajib_ditandai_di_daftar_preferensi(): void
    {
        $daftar = collect(Notifikasi::pilihanPreferensi(User::factory()->create()));
        $wajib = $daftar->firstWhere('kunci', Notifikasi::KEANGGOTAAN);

        $this->assertTrue($wajib['wajib']);
        $this->assertTrue($wajib['email']);

        // Kategori lain bisa diatur.
        $this->assertFalse($daftar->firstWhere('kunci', Notifikasi::PUSTAKA)['wajib']);
    }

    public function test_notifikasi_tanpa_pengguna_tidak_dikirim_ke_email(): void
    {
        // Mis. notifikasi ke alamat email pendaftar yang belum punya akun.
        $this->assertSame(['database'], Notifikasi::kanal(new class {}, Notifikasi::EVENT));
    }

    /* ===================== Bentuk data di pusat notifikasi ===================== */

    public function test_notifikasi_tersimpan_di_tabel_dengan_bentuk_yang_seragam(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $anggota = $this->buatAnggota($user, 'RAAB-2026-0009');

        $user->notify(new PengajuanDisetujui($anggota));

        $this->assertDatabaseCount('notifications', 1);

        $baris = Notifikasi::daftar($user->fresh());

        $this->assertCount(1, $baris);
        $this->assertSame(Notifikasi::KEANGGOTAAN, $baris[0]['kategori']);
        $this->assertSame('Pengajuan keanggotaanmu disetujui', $baris[0]['judul']);
        $this->assertStringContainsString('RAAB-2026-0009', $baris[0]['pesan']);
        $this->assertSame('/dasbor', $baris[0]['tautan']);
        $this->assertFalse($baris[0]['dibaca']);
        $this->assertNotSame('', $baris[0]['ikon']);
    }

    public function test_hitungan_belum_dibaca_berkurang_setelah_dibaca(): void
    {
        $user = User::factory()->create();
        $this->kirimDuaNotifikasi($user);

        $this->assertSame(2, Notifikasi::belumDibaca($user->fresh()));

        $id = Notifikasi::daftar($user->fresh())[0]['id'];

        $this->assertTrue(Notifikasi::tandaiDibaca($user->fresh(), $id));
        $this->assertSame(1, Notifikasi::belumDibaca($user->fresh()));

        $this->assertSame(1, Notifikasi::tandaiSemuaDibaca($user->fresh()));
        $this->assertSame(0, Notifikasi::belumDibaca($user->fresh()));
    }

    public function test_notifikasi_milik_orang_lain_tidak_bisa_ditandai(): void
    {
        $pemilik = User::factory()->create();
        $this->kirimDuaNotifikasi($pemilik);

        $id = Notifikasi::daftar($pemilik->fresh())[0]['id'];

        $penyusup = User::factory()->create();

        // Kepemilikan diperiksa lewat relasi, bukan lewat id saja — kalau tidak,
        // id notifikasi orang lain bisa ditandai dari sini.
        $this->assertFalse(Notifikasi::tandaiDibaca($penyusup, $id));
        $this->assertSame(2, Notifikasi::belumDibaca($pemilik->fresh()));
    }

    public function test_pesan_persetujuan_tetap_utuh_saat_nomor_anggota_belum_terbit(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $anggota = $this->buatAnggota($user);
        // Persetujuan dan penerbitan nomor adalah dua hal berbeda; yang satu
        // bisa sudah terjadi sementara yang lain belum.
        $anggota->nomor_anggota = null;
        $anggota->save();

        $user->notify(new PengajuanDisetujui($anggota->fresh()));

        $pesan = Notifikasi::daftar($user->fresh())[0]['pesan'];

        // Ditemukan lewat peramban: sebelumnya terbaca "Nomor anggotamu ." —
        // kalimat rusak yang membuat pengguna mengira nomornya hilang.
        $this->assertStringNotContainsString('Nomor anggotamu .', $pesan);
        $this->assertStringContainsString('Nomor anggota akan diterbitkan', $pesan);
    }

    public function test_saringan_kategori_menyaring_daftar(): void
    {
        $user = User::factory()->create();
        $this->kirimDuaNotifikasi($user);
        $this->sisipkanNotifikasiPustaka($user);

        $this->assertCount(2, Notifikasi::daftar($user->fresh(), Notifikasi::KEANGGOTAAN));
        $this->assertCount(1, Notifikasi::daftar($user->fresh(), Notifikasi::PUSTAKA));
        $this->assertCount(0, Notifikasi::daftar($user->fresh(), Notifikasi::KEUANGAN));
        $this->assertCount(3, Notifikasi::daftar($user->fresh()));
    }

    /**
     * Dua notifikasi yang MEMANG bisa diserialisasi.
     *
     * Sempat memakai `new MemberApplication` yang belum tersimpan. Notifikasi
     * berantrean menyimpan model lewat `SerializesModels`, yang menyimpannya
     * sebagai id lalu mengambilnya kembali — model yang belum tersimpan tidak
     * punya id, sehingga pengirimannya gagal dengan ModelNotFoundException.
     */
    private function kirimDuaNotifikasi(User $user): void
    {
        $anggota = $this->buatAnggota($user);

        $user->notify(new PengajuanDisetujui($anggota));
        $user->notify(new StatusKeanggotaanBerubah($anggota, Member::STATUS_MENUNGGU, 'Berkasmu sudah lengkap.'));
    }

    /**
     * Menyisipkan satu baris notifikasi kategori lain langsung ke tabel.
     *
     * Dipakai untuk menguji SISI PEMBACAAN (daftar & saringan kategori).
     * Sisi penulisannya sudah diuji lewat notifikasi sungguhan di atas, dan
     * membangun Pinjaman/Tagihan/Artikel lengkap hanya untuk memeriksa saringan
     * akan membuat uji ini menanggung beban tiga modul lain.
     */
    private function sisipkanNotifikasiPustaka(User $user): void
    {
        $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'uji-saringan',
            'data' => [
                'kategori' => Notifikasi::PUSTAKA,
                'ikon' => Notifikasi::ikon(Notifikasi::PUSTAKA),
                'judul' => 'Bukumu siap diambil',
                'pesan' => 'Buku yang kamu antrekan sudah tersedia.',
                'tautan' => '/pustaka',
            ],
        ]);
    }

    /* ===================== Halaman pusat notifikasi ===================== */

    public function test_pusat_notifikasi_butuh_masuk(): void
    {
        $this->get('/notifikasi')->assertRedirect('/login');
    }

    public function test_kader_melihat_pusat_notifikasi_dengan_kerangka_area_anggota(): void
    {
        $this->actingAs($this->kader())
            ->get('/notifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Anggota/Notifikasi', false));
    }

    public function test_pengurus_melihat_pusat_notifikasi_dengan_kerangka_panel(): void
    {
        $this->actingAs($this->pengurus())
            ->get('/notifikasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Panel/Notifikasi/Index', false));
    }

    public function test_membaca_notifikasi_lewat_halaman(): void
    {
        $user = $this->kader();
        $this->kirimDuaNotifikasi($user);

        $id = Notifikasi::daftar($user->fresh())[0]['id'];

        $this->actingAs($user)
            ->post("/notifikasi/{$id}/baca")
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame(1, Notifikasi::belumDibaca($user->fresh()));
    }

    public function test_menandai_notifikasi_orang_lain_menghasilkan_pesan_galat(): void
    {
        $pemilik = User::factory()->create();
        $this->kirimDuaNotifikasi($pemilik);

        $id = Notifikasi::daftar($pemilik->fresh())[0]['id'];

        $this->actingAs($this->kader())
            ->post("/notifikasi/{$id}/baca")
            ->assertRedirect()
            ->assertSessionHas('galat');

        $this->assertSame(2, Notifikasi::belumDibaca($pemilik->fresh()));
    }

    public function test_menandai_semua_sekaligus(): void
    {
        $user = $this->kader();
        $this->kirimDuaNotifikasi($user);

        $this->actingAs($user)
            ->post('/notifikasi/baca-semua')
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame(0, Notifikasi::belumDibaca($user->fresh()));
    }

    public function test_preferensi_email_disimpan_lewat_halaman(): void
    {
        $user = $this->kader();

        $this->actingAs($user)
            ->post('/notifikasi/preferensi', [
                'kategori' => [
                    Notifikasi::PUSTAKA => false,
                    Notifikasi::KEUANGAN => true,
                    // Sengaja dicoba dimatikan; harus tetap menyala.
                    Notifikasi::KEANGGOTAAN => false,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $tersimpan = $user->fresh()->preferensi_notifikasi;

        $this->assertFalse($tersimpan[Notifikasi::PUSTAKA]);
        $this->assertTrue($tersimpan[Notifikasi::KEUANGAN]);
        $this->assertTrue($tersimpan[Notifikasi::KEANGGOTAAN]);
    }

    public function test_lencana_notifikasi_dibagikan_ke_setiap_halaman(): void
    {
        $user = $this->kader();
        $this->kirimDuaNotifikasi($user);

        $this->actingAs($user)
            ->get('/dasbor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('panel.notifikasi_belum_dibaca', 2));
    }

    /* ===================== Laporan ===================== */

    public function test_halaman_laporan_butuh_izin(): void
    {
        $this->actingAs($this->kader())->get('/panel/laporan')->assertForbidden();

        $this->actingAs($this->pengurus())->get('/panel/laporan')->assertOk();
    }

    public function test_sekretaris_melihat_keempat_laporan(): void
    {
        $this->actingAs($this->pengurus())
            ->get('/panel/laporan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Laporan/Index', false)
                ->has('tersedia', 4)
                ->has('tertutup', 0));
    }

    public function test_bendahara_tidak_melihat_satu_pun_laporan_data(): void
    {
        // Bendahara memegang `reports.generate` tetapi tidak memegang satu pun
        // izin data mentahnya. Halaman laporan tidak boleh menjadi jalan pintas.
        $this->actingAs($this->pengurus('bendahara'))
            ->get('/panel/laporan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tersedia', 0)
                ->has('tertutup', 4));
    }

    public function test_pengguna_yang_hanya_punya_sebagian_izin_hanya_melihat_sebagian_laporan(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(['reports.generate', 'activities.view']);

        $this->actingAs($user)
            ->get('/panel/laporan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tersedia', 1)
                ->where('tersedia.0.kunci', 'kegiatan')
                ->has('tertutup', 3));
    }

    public function test_ekspor_laporan_menghasilkan_csv(): void
    {
        $respons = $this->actingAs($this->pengurus())->get('/panel/laporan/kegiatan/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $isi = $respons->getContent();

        $this->assertStringContainsString('sep=;', $isi);
        $this->assertStringContainsString('Kode', $isi);
        $this->assertStringContainsString('Hadir', $isi);
    }

    public function test_ekspor_laporan_tanpa_izin_data_ditolak(): void
    {
        // Bendahara boleh membuka halaman laporan, tetapi tidak boleh mengunduh
        // laporan yang datanya bukan haknya.
        $this->actingAs($this->pengurus('bendahara'))
            ->get('/panel/laporan/kegiatan/ekspor')
            ->assertForbidden();

        $this->actingAs($this->pengurus('bendahara'))
            ->get('/panel/laporan/poin/ekspor')
            ->assertForbidden();
    }

    public function test_ekspor_laporan_butuh_izin_ekspor(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(['reports.generate', 'activities.view']);

        // Punya izin datanya, tetapi TIDAK punya izin ekspor.
        $this->actingAs($user)->get('/panel/laporan/kegiatan/ekspor')->assertForbidden();
    }

    public function test_jenis_laporan_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->pengurus())->get('/panel/laporan/entah_apa/ekspor')->assertNotFound();
    }

    public function test_ekspor_menerima_rentang_tanggal(): void
    {
        $respons = $this->actingAs($this->pengurus())
            ->get('/panel/laporan/presensi/ekspor?dari=2026-09-01&sampai=2026-09-30');

        $respons->assertOk();
        $this->assertStringContainsString('Status', $respons->getContent());
    }

    public function test_rentang_tanggal_yang_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->pengurus())
            ->get('/panel/laporan/kegiatan/ekspor?dari=bukan-tanggal')
            ->assertSessionHasErrors('dari');
    }
}
