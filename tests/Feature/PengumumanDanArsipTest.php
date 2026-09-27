<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\Member;
use App\Models\User;
use App\Services\Arsip;
use App\Services\Pengumuman;
use App\Support\Audiens;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Pengumuman & Arsip dokumen.
 *
 * DUA HAL YANG PALING PENTING DIUJI DI SINI:
 *  - pengumuman yang belum waktunya / sudah kedaluwarsa tidak boleh tayang;
 *  - dokumen yang dibatasi audiens tidak boleh bisa diunduh lewat alamat
 *    langsung, meskipun ia tidak muncul di daftar.
 */
class PengumumanDanArsipTest extends TestCase
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

    private function pengurus(string $peran = 'sekretaris'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    private function anggota(string $status = Member::STATUS_AKTIF): Member
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $anggota = new Member;
        $anggota->user_id = $user->id;
        $anggota->nama_lengkap = 'Kader '.ucfirst($status);
        $anggota->status = $status;
        $anggota->jalur = $status === Member::STATUS_ALUMNI ? Member::JALUR_ALUMNI : Member::JALUR_KADER;
        $anggota->save();

        return $anggota;
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function pengumuman(array $ganti = []): Announcement
    {
        return app(Pengumuman::class)->simpan(array_merge([
            'judul' => 'Rapat evaluasi bulanan',
            'isi' => 'Rapat evaluasi bulanan diadakan hari Sabtu pukul 19.30 di sekretariat rayon.',
            'tipe' => Announcement::TIPE_INTERNAL,
            'target_audience' => [Audiens::KADER],
            'is_pinned' => false,
            'publish_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'expire_at' => null,
        ], $ganti), $this->pengurus());
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function dokumen(array $ganti = [], ?User $petugas = null): Document
    {
        return app(Arsip::class)->simpan(array_merge([
            'judul' => 'AD/ART PMII RAAB',
            'keterangan' => 'Anggaran dasar dan anggaran rumah tangga rayon.',
            'kategori' => Document::KATEGORI_AD_ART,
            'nomor' => '012/AD/RAAB/IX/2026',
            'tanggal_dokumen' => now()->format('Y-m-d'),
            'akses' => [Audiens::PUBLIK],
        ], $ganti), $petugas ?? $this->pengurus());
    }

    /* ===================== Audiens ===================== */

    public function test_tamu_hanya_memiliki_audiens_publik(): void
    {
        $this->assertSame([Audiens::PUBLIK], Audiens::dimiliki(null));
    }

    public function test_audiens_mengikuti_status_keanggotaan_dan_peran(): void
    {
        $kader = $this->anggota(Member::STATUS_AKTIF);
        $this->assertEqualsCanonicalizing([Audiens::PUBLIK, Audiens::KADER], Audiens::dimiliki($kader->user));

        $alumni = $this->anggota(Member::STATUS_ALUMNI);
        $this->assertEqualsCanonicalizing([Audiens::PUBLIK, Audiens::ALUMNI], Audiens::dimiliki($alumni->user));

        $pengurus = $this->pengurus();
        $this->assertContains(Audiens::PENGURUS, Audiens::dimiliki($pengurus));
    }

    public function test_pengurus_boleh_membaca_semua_audiens(): void
    {
        $pengurus = $this->pengurus();

        $this->assertTrue(Audiens::boleh([Audiens::PENGURUS], $pengurus));
        $this->assertTrue(Audiens::boleh([Audiens::KADER], $pengurus));
        $this->assertTrue(Audiens::boleh([Audiens::ALUMNI], $pengurus));
        $this->assertTrue(Audiens::boleh([Audiens::PUBLIK], $pengurus));
    }

    public function test_kader_tidak_boleh_membaca_dokumen_khusus_pengurus(): void
    {
        $kader = $this->anggota();

        $this->assertFalse(Audiens::boleh([Audiens::PENGURUS], $kader->user));
        $this->assertFalse(Audiens::boleh([Audiens::ALUMNI], $kader->user));
        $this->assertTrue(Audiens::boleh([Audiens::KADER], $kader->user));
    }

    /* ===================== Pengumuman: masa berlaku & tipe ===================== */

    public function test_pengumuman_publik_tayang_di_halaman_publik(): void
    {
        $this->pengumuman([
            'judul' => 'Pendaftaran Mapaba dibuka',
            'tipe' => Announcement::TIPE_PUBLIK,
            'target_audience' => [Audiens::PUBLIK],
        ]);

        $this->get('/pengumuman')
            ->assertOk()
            ->assertSee('Pendaftaran Mapaba dibuka', false);
    }

    public function test_pengumuman_internal_tidak_tayang_di_halaman_publik(): void
    {
        $this->pengumuman(['judul' => 'Rapat internal pengurus']);

        $this->get('/pengumuman')
            ->assertOk()
            ->assertDontSee('Rapat internal pengurus', false);
    }

    public function test_pengumuman_yang_belum_waktunya_tidak_tayang_lebih_awal(): void
    {
        $this->pengumuman([
            'judul' => 'Pengumuman besok',
            'tipe' => Announcement::TIPE_PUBLIK,
            'target_audience' => [Audiens::PUBLIK],
            'publish_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->get('/pengumuman')->assertDontSee('Pengumuman besok', false);
        $this->assertCount(0, app(Pengumuman::class)->untukPublik());
    }

    public function test_pengumuman_yang_kedaluwarsa_berhenti_tayang(): void
    {
        $this->pengumuman([
            'judul' => 'Pendaftaran sudah tutup',
            'tipe' => Announcement::TIPE_PUBLIK,
            'target_audience' => [Audiens::PUBLIK],
            'publish_at' => now()->subMonth()->format('Y-m-d H:i:s'),
            'expire_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $this->get('/pengumuman')->assertDontSee('Pendaftaran sudah tutup', false);
    }

    public function test_pengumuman_publik_wajib_menyertakan_audiens_umum(): void
    {
        $this->expectException(ValidationException::class);

        // Kalau lolos, pengumuman ini akan tayang di /pengumuman tetapi tidak
        // bisa dibaca siapa pun — kesalahan yang tidak kelihatan sampai ada
        // yang mengeluh.
        $this->pengumuman([
            'tipe' => Announcement::TIPE_PUBLIK,
            'target_audience' => [Audiens::KADER],
        ]);
    }

    public function test_audiens_kosong_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->pengumuman(['target_audience' => []]);
    }

    public function test_isi_terlalu_pendek_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->pengumuman(['isi' => 'Rapat.']);
    }

    public function test_masa_berlaku_harus_setelah_mulai_tayang(): void
    {
        $this->expectException(ValidationException::class);

        $this->pengumuman([
            'publish_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'expire_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_judul_berubah_membuat_slug_baru(): void
    {
        $p = $this->pengumuman(['judul' => 'Judul Pertama']);
        $slugLama = $p->slug;

        app(Pengumuman::class)->perbarui($p, [
            'judul' => 'Judul Kedua',
            'isi' => $p->isiTeks(),
            'tipe' => $p->tipe,
            'target_audience' => $p->target_audience,
        ], $this->pengurus());

        $this->assertNotSame($slugLama, $p->fresh()->slug);
        $this->assertSame('judul-kedua', $p->fresh()->slug);
    }

    public function test_pengumuman_disematkan_naik_ke_puncak_daftar(): void
    {
        $biasa = $this->pengumuman(['judul' => 'Pengumuman Biasa', 'is_pinned' => false]);
        $disematkan = $this->pengumuman([
            'judul' => 'Pengumuman Disematkan',
            'is_pinned' => true,
            'publish_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
        ]);

        $urut = app(Pengumuman::class)->untuk($this->pengurus())->pluck('id')->all();

        $this->assertSame($disematkan->id, $urut[0], 'Yang disematkan harus paling atas meski lebih lama.');
        $this->assertContains($biasa->id, $urut);
    }

    public function test_menghapus_pengumuman_tidak_memusnahkannya(): void
    {
        $p = $this->pengumuman();

        app(Pengumuman::class)->hapus($p, $this->pengurus());

        $this->assertSoftDeleted('announcements', ['id' => $p->id]);
        $this->assertNotNull(Announcement::withTrashed()->find($p->id));
    }

    /* ===================== Pengumuman: audiens di area anggota ===================== */

    public function test_pengumuman_khusus_pengurus_tidak_dikirim_ke_kader(): void
    {
        $this->pengumuman([
            'judul' => 'Rapat pengurus saja',
            'target_audience' => [Audiens::PENGURUS],
        ]);

        $kader = $this->anggota();

        $this->actingAs($kader->user)
            ->get('/pengumuman-internal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anggota/Pengumuman', false)
                ->where('total', 0));

        // Yang berhak tetap menerimanya.
        $this->actingAs($this->pengurus())
            ->get('/pengumuman-internal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('total', 1));
    }

    public function test_kader_melihat_pengumuman_kader_dan_publik_sekaligus(): void
    {
        $this->pengumuman(['judul' => 'Untuk Kader', 'target_audience' => [Audiens::KADER]]);
        $this->pengumuman([
            'judul' => 'Untuk Umum',
            'tipe' => Announcement::TIPE_PUBLIK,
            'target_audience' => [Audiens::PUBLIK],
        ]);

        $kader = $this->anggota();

        $this->actingAs($kader->user)
            ->get('/pengumuman-internal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('total', 2));
    }

    public function test_area_anggota_butuh_masuk(): void
    {
        $this->get('/pengumuman-internal')->assertRedirect('/login');
    }

    /* ===================== Pengumuman: izin panel ===================== */

    public function test_panel_pengumuman_butuh_izin(): void
    {
        $kader = $this->anggota();

        $this->actingAs($kader->user)->get('/panel/pengumuman')->assertForbidden();

        $this->actingAs($this->pengurus())->get('/panel/pengumuman')->assertOk();
    }

    public function test_konten_manager_boleh_mengelola_pengumuman(): void
    {
        $this->actingAs($this->pengurus('konten_manager'))
            ->post('/panel/pengumuman', [
                'judul' => 'Dari Konten Manager',
                'isi' => 'Isi pengumuman yang cukup panjang untuk lolos pemeriksaan.',
                'tipe' => Announcement::TIPE_INTERNAL,
                'target_audience' => [Audiens::KADER],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('announcements', ['tipe' => Announcement::TIPE_INTERNAL]);
    }

    public function test_bendahara_tidak_boleh_mengelola_pengumuman(): void
    {
        $this->actingAs($this->pengurus('bendahara'))
            ->post('/panel/pengumuman', [
                'judul' => 'Tidak boleh',
                'isi' => 'Isi pengumuman yang cukup panjang untuk lolos pemeriksaan.',
                'tipe' => Announcement::TIPE_INTERNAL,
                'target_audience' => [Audiens::KADER],
            ])
            ->assertForbidden();
    }

    public function test_gagal_validasi_pesan_galat_tampil_di_panel(): void
    {
        $this->actingAs($this->pengurus())
            ->post('/panel/pengumuman', [
                'judul' => 'Pengumuman publik tanpa audiens umum',
                'isi' => 'Isi pengumuman yang cukup panjang untuk lolos pemeriksaan.',
                'tipe' => Announcement::TIPE_PUBLIK,
                'target_audience' => [Audiens::KADER],
            ])
            ->assertSessionHas('galat');

        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_ekspor_csv_pengumuman(): void
    {
        $this->pengumuman();

        $respons = $this->actingAs($this->pengurus())->get('/panel/pengumuman/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Rapat evaluasi bulanan', $respons->getContent());
    }

    /* ===================== Arsip: kendali akses ===================== */

    public function test_berkas_arsip_disimpan_di_disk_privat_bukan_publik(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->dokumen();
        app(Arsip::class)->simpan([
            'judul' => 'Template Surat Undangan',
            'kategori' => Document::KATEGORI_TEMPLATE_SURAT,
            'akses' => [Audiens::PENGURUS],
        ], $this->pengurus(), UploadedFile::fake()->create('template-surat.pdf', 40, 'application/pdf'));

        $media = Media::query()->latest('id')->firstOrFail();

        // Kalau berkasnya ada di disk publik, kendali akses per audiens cuma
        // hiasan: siapa pun yang menebak alamatnya bisa mengunduhnya.
        $this->assertSame('local', $media->disk);
        $this->assertSame('arsip', $media->collection_name);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
        Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
    }

    public function test_dokumen_umum_bisa_diunduh_tamu(): void
    {
        Storage::fake('local');

        $dokumen = $this->dokumen([], $this->pengurus());
        app(Arsip::class)->perbarui($dokumen, [
            'judul' => $dokumen->judulTeks(),
            'kategori' => $dokumen->kategori,
            'akses' => [Audiens::PUBLIK],
        ], $this->pengurus(), UploadedFile::fake()->create('ad-art.pdf', 40, 'application/pdf'));

        $this->get("/arsip/{$dokumen->id}/unduh")->assertOk();
    }

    public function test_dokumen_kader_tidak_bisa_diunduh_tamu(): void
    {
        Storage::fake('local');

        $petugas = $this->pengurus();
        $dokumen = $this->dokumen(['akses' => [Audiens::KADER]], $petugas);

        app(Arsip::class)->perbarui($dokumen, [
            'judul' => $dokumen->judulTeks(),
            'kategori' => $dokumen->kategori,
            'akses' => [Audiens::KADER],
        ], $petugas, UploadedFile::fake()->create('notulen.pdf', 40, 'application/pdf'));

        // Inilah yang paling mudah terlewat: daftar sudah disaring, tetapi
        // alamat unduhan langsung harus tetap ditolak.
        $this->get("/arsip/{$dokumen->id}/unduh")->assertForbidden();

        $kader = $this->anggota();
        $this->actingAs($kader->user)->get("/arsip/{$dokumen->id}/unduh")->assertOk();
    }

    public function test_dokumen_pengurus_tidak_bisa_diunduh_kader(): void
    {
        Storage::fake('local');

        $petugas = $this->pengurus();
        $dokumen = $this->dokumen(['akses' => [Audiens::PENGURUS]], $petugas);

        app(Arsip::class)->perbarui($dokumen, [
            'judul' => $dokumen->judulTeks(),
            'kategori' => $dokumen->kategori,
            'akses' => [Audiens::PENGURUS],
        ], $petugas, UploadedFile::fake()->create('rahasia.pdf', 40, 'application/pdf'));

        $kader = $this->anggota();
        $this->actingAs($kader->user)->get("/arsip/{$dokumen->id}/unduh")->assertForbidden();

        $this->actingAs($petugas)->get("/arsip/{$dokumen->id}/unduh")->assertOk();
    }

    public function test_daftar_arsip_anggota_hanya_memuat_yang_berhak(): void
    {
        $petugas = $this->pengurus();

        $this->dokumen(['judul' => 'AD/ART untuk Umum', 'akses' => [Audiens::PUBLIK]], $petugas);
        $this->dokumen(['judul' => 'Notulen Khusus Pengurus', 'akses' => [Audiens::PENGURUS]], $petugas);

        $kader = $this->anggota();

        $this->actingAs($kader->user)
            ->get('/arsip-internal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anggota/Arsip', false)
                ->where('total', 1)
                ->where('daftar.0.judul', 'AD/ART untuk Umum'));
    }

    public function test_halaman_arsip_publik_hanya_memuat_dokumen_umum(): void
    {
        $petugas = $this->pengurus();

        $this->dokumen(['judul' => 'AD/ART Terbuka', 'akses' => [Audiens::PUBLIK]], $petugas);
        $this->dokumen(['judul' => 'Notulen Tertutup', 'akses' => [Audiens::PENGURUS]], $petugas);

        $this->get('/arsip')
            ->assertOk()
            ->assertSee('AD/ART Terbuka', false)
            ->assertDontSee('Notulen Tertutup', false);
    }

    public function test_alumni_tidak_melihat_dokumen_khusus_kader(): void
    {
        $petugas = $this->pengurus();

        $this->dokumen(['judul' => 'Dokumen Kader Saja', 'akses' => [Audiens::KADER]], $petugas);

        $alumni = $this->anggota(Member::STATUS_ALUMNI);

        $this->actingAs($alumni->user)
            ->get('/arsip-internal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('total', 0));
    }

    /* ===================== Arsip: penyimpanan & hapus ===================== */

    public function test_akses_kosong_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->dokumen(['akses' => []]);
    }

    public function test_kategori_tidak_dikenal_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->dokumen(['kategori' => 'entah_apa']);
    }

    public function test_menghapus_dokumen_tidak_menghapus_berkasnya(): void
    {
        Storage::fake('local');

        $petugas = $this->pengurus();
        $dokumen = $this->dokumen([], $petugas);

        app(Arsip::class)->perbarui($dokumen, [
            'judul' => $dokumen->judulTeks(),
            'kategori' => $dokumen->kategori,
            'akses' => [Audiens::PUBLIK],
        ], $petugas, UploadedFile::fake()->create('ad-art.pdf', 40, 'application/pdf'));

        $media = Media::query()->latest('id')->firstOrFail();

        app(Arsip::class)->hapus($dokumen->fresh(), $petugas);

        $this->assertSoftDeleted('documents', ['id' => $dokumen->id]);
        // Salah hapus arsip tidak boleh berarti dokumennya hilang selamanya.
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_dokumen_tanpa_berkas_tetap_bisa_dicatat(): void
    {
        $dokumen = $this->dokumen(['tautan_luar' => 'https://contoh.test/dokumen']);

        // "Belum ada berkas" adalah keadaan yang sah, bukan kerusakan: dokumen
        // boleh dicatat lebih dulu, berkasnya menyusul.
        $this->assertTrue($dokumen->punyaBerkas());
        $this->assertNull($dokumen->namaBerkas());
        $this->assertNull($dokumen->ukuranTeks());

        $tanpaApaApa = $this->dokumen(['judul' => 'Belum ada berkas sama sekali']);
        $this->assertFalse($tanpaApaApa->punyaBerkas());
    }

    /* ===================== Arsip: izin panel ===================== */

    public function test_bendahara_boleh_melihat_arsip_tetapi_tidak_mengunggah(): void
    {
        $bendahara = $this->pengurus('bendahara');

        $this->actingAs($bendahara)->get('/panel/arsip')->assertOk();

        $this->actingAs($bendahara)
            ->post('/panel/arsip', [
                'judul' => 'Tidak boleh diunggah',
                'kategori' => Document::KATEGORI_LAINNYA,
                'akses' => [Audiens::PENGURUS],
            ])
            ->assertForbidden();
    }

    public function test_kader_tidak_bisa_membuka_panel_arsip(): void
    {
        $kader = $this->anggota();

        $this->actingAs($kader->user)->get('/panel/arsip')->assertForbidden();
    }

    public function test_sekretaris_mengunggah_dokumen_lewat_panel(): void
    {
        Storage::fake('local');

        $this->actingAs($this->pengurus())
            ->post('/panel/arsip', [
                'judul' => 'Notulen Rapat Pleno',
                'kategori' => Document::KATEGORI_HASIL_RAPAT,
                'akses' => [Audiens::PENGURUS],
                'berkas' => UploadedFile::fake()->create('notulen.pdf', 60, 'application/pdf'),
            ])
            ->assertRedirect();

        $dokumen = Document::query()->firstOrFail();

        $this->assertTrue($dokumen->punyaBerkas());
        $this->assertNotNull($dokumen->namaBerkas());
        $this->assertNotNull($dokumen->ukuranTeks());
    }

    public function test_jenis_berkas_palsu_ditolak(): void
    {
        Storage::fake('local');

        $this->actingAs($this->pengurus())
            ->post('/panel/arsip', [
                'judul' => 'Berkas Mencurigakan',
                'kategori' => Document::KATEGORI_LAINNYA,
                'akses' => [Audiens::PENGURUS],
                'berkas' => UploadedFile::fake()->create('jahat.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(0, Document::query()->count());
    }

    public function test_ekspor_csv_arsip(): void
    {
        $this->dokumen();

        $respons = $this->actingAs($this->pengurus())->get('/panel/arsip/ekspor');

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('AD/ART PMII RAAB', $respons->getContent());
    }

    /* ===================== Menu publik ===================== */

    public function test_menu_pengumuman_dan_arsip_muncul_di_navbar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('public.pengumuman'), false)
            ->assertSee(route('public.arsip'), false);
    }
}
