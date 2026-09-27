<?php

namespace Tests\Feature;

use App\Jobs\TerjemahkanArtikel;
use App\Models\Article;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Penerjemah;
use App\Services\Redaksi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Fitur penutup Fase 3: ekspor PDF berita acara, terjemahan otomatis
 * Indonesia→Inggris, dan halaman statistik publikasi.
 *
 * HAL YANG PALING DIJAGA:
 *  1. PDF hanya untuk berita acara — artikel biasa tidak boleh mengunduhnya.
 *  2. Penerjemah yang belum dikonfigurasi harus MENGATAKAN alasannya, bukan
 *     gagal diam-diam.
 *  3. Istilah organisasi (PMII, Mapaba, …) tidak boleh ikut diterjemahkan.
 */
class TerjemahanDanPdfTest extends TestCase
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

    private function pengurus(string $peran): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function data(array $ganti = []): array
    {
        return array_merge([
            'tipe' => Article::TIPE_BERITA,
            'judul' => ['id' => 'Mapaba 2026 Berlangsung Meriah', 'en' => ''],
            'ringkasan' => ['id' => 'Kegiatan Mapaba tahun ini diikuti 120 peserta baru.', 'en' => ''],
            'konten' => ['id' => '<p>'.str_repeat('Mapaba berlangsung dengan penuh semangat. ', 6).'</p>', 'en' => ''],
            'tag' => 'mapaba',
        ], $ganti);
    }

    private function artikel(User $penulis, array $ganti = []): Article
    {
        return app(Redaksi::class)->simpan(new Article, $this->data($ganti), $penulis);
    }

    /**
     * Berita acara lengkap yang sudah terbit.
     */
    private function beritaAcara(User $penulis): Article
    {
        $artikel = $this->artikel($penulis, [
            'tipe' => Article::TIPE_BERITA_ACARA,
            'nomor_dokumen' => '012/BA/RAAB/IX/2026',
            'tanggal_agenda' => now()->format('Y-m-d'),
            'agenda' => 'Pembahasan program kerja semester ganjil.',
            'keputusan' => 'Program kerja disetujui dengan catatan revisi anggaran.',
            'penandatangan' => 'Ahmad Fauzi',
            'jabatan_penandatangan' => 'Sekretaris Rayon',
        ]);

        app(Redaksi::class)->terbitkan($artikel, $penulis);

        return $artikel->fresh();
    }

    /**
     * @param  array<string, mixed>  $nilai
     */
    private function setPengaturan(string $kunci, array $nilai): void
    {
        // Menyimpan lewat model agar cache Pengaturan otomatis dibuang.
        SiteSetting::query()->where('kunci', $kunci)->firstOrFail()
            ->forceFill(['nilai' => $nilai])
            ->save();
    }

    /* ====================== PDF berita acara ====================== */

    public function test_berita_acara_dapat_diunduh_sebagai_pdf(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $artikel = $this->beritaAcara($sekretaris);

        $respons = $this->actingAs($sekretaris)->get('/panel/artikel/'.$artikel->id.'/pdf');

        $respons->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $respons->headers->get('content-type'));
        $this->assertStringContainsString(
            'berita-acara-012-ba-raab-ix-2026.pdf',
            (string) $respons->headers->get('content-disposition'),
        );
    }

    public function test_isi_pdf_memuat_nomor_dokumen_dan_penandatangan(): void
    {
        $sekretaris = $this->pengurus('sekretaris');
        $artikel = $this->beritaAcara($sekretaris);

        // Nama berkas sudah cukup untuk membuktikan nomor dokumen masuk ke dokumen.
        // Untuk isi, kita periksa lewat render tampilan PDF-nya langsung.
        $html = view('pdf.berita-acara', ['artikel' => $artikel->loadMissing('penulis')])->render();

        $this->assertStringContainsString('012/BA/RAAB/IX/2026', $html);
        $this->assertStringContainsString('Ahmad Fauzi', $html);
        $this->assertStringContainsString('Sekretaris Rayon', $html);
        $this->assertStringContainsString('Pembahasan program kerja semester ganjil', $html);
    }

    public function test_artikel_biasa_tidak_dapat_diunduh_pdf(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        $this->actingAs($penulis)
            ->get('/panel/artikel/'.$artikel->id.'/pdf')
            ->assertForbidden();
    }

    public function test_pdf_perlu_login(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->beritaAcara($penulis);

        $this->get('/panel/artikel/'.$artikel->id.'/pdf')->assertRedirect('/login');
    }

    /* ====================== Halaman statistik ====================== */

    public function test_pengelola_dapat_membuka_statistik_publikasi(): void
    {
        $penulis = $this->pengurus('konten_manager');

        $this->actingAs($penulis)
            ->get('/panel/statistik-publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Panel/Artikel/Statistik', false)
                ->has('ringkasan')
                ->has('terpopuler')
                ->has('perPenulis')
                ->has('perTipe'),
            );
    }

    public function test_statistik_menghitung_jumlah_dibaca(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        Article::query()->whereKey($artikel->id)->update(['dilihat' => 42]);

        $this->actingAs($penulis)
            ->get('/panel/statistik-publikasi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ringkasan.terbit', 1)
                ->where('ringkasan.total_dibaca', 42)
                ->where('ringkasan.rata_dibaca', 42)
                ->where('terpopuler.0.dilihat', 42)
                ->where('perPenulis.0.jumlah', 1)
                ->where('perPenulis.0.total_dibaca', 42),
            );
    }

    public function test_statistik_tertutup_bagi_kader_biasa(): void
    {
        $kader = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($kader)->get('/panel/statistik-publikasi')->assertForbidden();
    }

    /* ====================== Penerjemah ====================== */

    public function test_penerjemah_tidak_siap_bila_adaptor_none(): void
    {
        $penerjemah = app(Penerjemah::class);

        $this->assertSame(Penerjemah::DRIVER_NONE, $penerjemah->driver());
        $this->assertFalse($penerjemah->tersedia());
        $this->assertNotNull($penerjemah->alasanTidakTersedia());
        $this->assertStringContainsString('Tidak menerjemahkan', (string) $penerjemah->alasanTidakTersedia());
    }

    public function test_penerjemah_tidak_siap_bila_kunci_belum_diisi(): void
    {
        $this->setPengaturan('penerjemah_driver', ['id' => Penerjemah::DRIVER_DEEPL]);
        config(['services.penerjemah.kunci' => null]);

        $penerjemah = app(Penerjemah::class);

        $this->assertFalse($penerjemah->tersedia());
        $this->assertStringContainsString('PENERJEMAH_KUNCI', (string) $penerjemah->alasanTidakTersedia());
    }

    public function test_penerjemah_siap_bila_adaptor_dan_kunci_terisi(): void
    {
        $this->setPengaturan('penerjemah_driver', ['id' => Penerjemah::DRIVER_DEEPL]);
        config(['services.penerjemah.kunci' => 'kunci-uji']);

        $penerjemah = app(Penerjemah::class);

        $this->assertTrue($penerjemah->tersedia());
        $this->assertNull($penerjemah->alasanTidakTersedia());
    }

    public function test_tombol_terjemahkan_mengabari_bila_adaptor_belum_siap(): void
    {
        Queue::fake();

        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        $this->actingAs($penulis)
            ->from('/panel/artikel/'.$artikel->id.'/sunting')
            ->post('/panel/artikel/'.$artikel->id.'/terjemahkan')
            ->assertSessionHas('galat');

        Queue::assertNotPushed(TerjemahkanArtikel::class);
    }

    public function test_tombol_terjemahkan_menaruh_job_di_antrean(): void
    {
        Queue::fake();

        $this->setPengaturan('penerjemah_driver', ['id' => Penerjemah::DRIVER_DEEPL]);
        config(['services.penerjemah.kunci' => 'kunci-uji']);

        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        $this->actingAs($penulis)
            ->from('/panel/artikel/'.$artikel->id.'/sunting')
            ->post('/panel/artikel/'.$artikel->id.'/terjemahkan')
            ->assertSessionHas('sukses');

        Queue::assertPushed(
            TerjemahkanArtikel::class,
            fn (TerjemahkanArtikel $job): bool => $job->articleId === $artikel->id && $job->paksa === true,
        );
    }

    /* ====================== Job terjemahan ====================== */

    public function test_job_mengisi_versi_inggris_artikel(): void
    {
        $this->setPengaturan('penerjemah_driver', ['id' => Penerjemah::DRIVER_DEEPL]);
        config(['services.penerjemah.kunci' => 'kunci-uji']);

        // Layanan tiruan mengembalikan teks apa adanya (sesuai permintaan).
        Http::fake(function ($request) {
            $teks = $request->data()['text'] ?? '';

            return Http::response(['translations' => [['text' => 'EN: '.$teks]]]);
        });

        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis, ['judul' => ['id' => 'Berita Penting', 'en' => '']]);

        (new TerjemahkanArtikel($artikel->id))->handle(app(Penerjemah::class));

        $artikel->refresh();

        $this->assertSame('EN: Berita Penting', $artikel->getTranslation('judul', 'en'));
        $this->assertStringContainsString('EN:', (string) $artikel->getTranslation('ringkasan', 'en'));
        $this->assertStringContainsString('EN:', (string) $artikel->getTranslation('konten', 'en'));
        $this->assertGreaterThan(0, $artikel->waktu_baca_menit);
    }

    public function test_glosarium_dilindungi_dari_penerjemahan(): void
    {
        $this->setPengaturan('penerjemah_driver', ['id' => Penerjemah::DRIVER_DEEPL]);
        config(['services.penerjemah.kunci' => 'kunci-uji']);

        $terkirim = [];

        // Meniru mesin penerjemah: setiap kata diterjemahkan, penanda dibiarkan.
        Http::fake(function ($request) use (&$terkirim) {
            $teks = (string) ($request->data()['text'] ?? '');
            $terkirim[] = $teks;

            return Http::response(['translations' => [['text' => strtoupper($teks)]]]);
        });

        $hasil = app(Penerjemah::class)->terjemahkan('<p>PMII Rayon Ali Ahmad Baktsir menggelar Mapaba bersama LSO.</p>');

        $this->assertNotNull($hasil);

        // Penanda sementara dikirim ke layanan, bukan nama lembaganya.
        $this->assertStringNotContainsString('PMII', implode(' ', $terkirim));
        $this->assertStringContainsString('XXTERM', implode(' ', $terkirim));

        // Setelah dikembalikan, istilah aslinya utuh.
        $this->assertStringContainsString('PMII Rayon Ali Ahmad Baktsir', (string) $hasil);
        $this->assertStringContainsString('Mapaba', (string) $hasil);
        $this->assertStringContainsString('LSO', (string) $hasil);
        $this->assertStringNotContainsString('XXTERM', (string) $hasil);
    }

    public function test_penerjemah_mengembalikan_null_bila_belum_siap(): void
    {
        $this->assertNull(app(Penerjemah::class)->terjemahkan('<p>Halo</p>'));
    }

    public function test_job_berhenti_dengan_tenang_bila_penerjemah_tidak_siap(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);

        (new TerjemahkanArtikel($artikel->id))->handle(app(Penerjemah::class));

        $this->assertSame('', (string) $artikel->fresh()->getTranslation('judul', 'en'));
    }

    /* ====================== Pengaturan ====================== */

    public function test_seeder_membuat_pengaturan_penerjemah_bertipe_pilihan(): void
    {
        $setting = SiteSetting::query()->where('kunci', 'penerjemah_driver')->firstOrFail();

        $this->assertSame(SiteSetting::TIPE_PILIHAN, $setting->tipe);
        $this->assertSame(Penerjemah::DRIVER_NONE, $setting->nilai);
        $this->assertSame(
            [Penerjemah::DRIVER_NONE, Penerjemah::DRIVER_DEEPL, Penerjemah::DRIVER_GOOGLE],
            $setting->pilihan,
        );
    }

    public function test_halaman_pengaturan_menampilkan_dropdown_terjemahan(): void
    {
        $superadmin = $this->pengurus('superadmin');

        $this->actingAs($superadmin)
            ->get('/panel/pengaturan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('grup', fn ($grup) => collect($grup)->contains(
                    fn ($item): bool => collect($item['item'] ?? [])->contains(
                        fn (array $baris): bool => $baris['kunci'] === 'penerjemah_driver'
                            && $baris['pilihan']['deepl'] === 'DeepL',
                    ),
                )),
            );
    }

    /**
     * Kolom terjemahan harus SUDAH diselesaikan saat model diserialisasi.
     *
     * spatie/laravel-translatable menyimpan satu kolom berisi seluruh bahasa:
     * `{"id":"Kegiatan","en":"Activities"}`. Membaca `$model->nama` sudah benar,
     * tetapi saat model diubah menjadi JSON — persis yang dilakukan Inertia
     * sebelum mengirim prop ke halaman Vue — yang keluar adalah peta bahasanya.
     *
     * Akibatnya filter Kategori di halaman Publikasi menampilkan:
     *
     *     { "id": "Kegiatan", "en": "Activities" }
     *
     * kepada Konten Manager, yang tentu tidak bisa memakainya.
     *
     * Uji ini menjaga agar seluruh model berkolom terjemahan tetap
     * menyelesaikannya saat diserialisasi.
     */
    public function test_kolom_terjemahan_diselesaikan_saat_model_disirialisasi(): void
    {
        app()->setLocale('id');

        $kategori = \App\Models\ArticleCategory::query()->create([
            'nama' => ['id' => 'Kegiatan', 'en' => 'Activities'],
            'slug' => ['id' => 'kegiatan', 'en' => 'activities'],
            'urutan' => 1,
            'aktif' => true,
        ]);

        $this->assertSame('Kegiatan', $kategori->toArray()['nama']);
        $this->assertSame('Kegiatan', json_decode($kategori->toJson(), true)['nama']);

        app()->setLocale('en');
        $this->assertSame('Activities', $kategori->fresh()->toArray()['nama']);
    }

    /**
     * Kolom yang tidak diambil tidak boleh muncul sebagai null.
     *
     * Beberapa controller sengaja mengambil sebagian kolom
     * (`get(['id','nama'])`). Kalau penyelesaian terjemahan menambahkan kunci
     * yang tidak diminta, bentuk datanya berubah diam-diam dan hal lain bisa
     * ikut rusak.
     */
    public function test_kolom_yang_tidak_diambil_tidak_ditambahkan(): void
    {
        $kategori = \App\Models\ArticleCategory::query()->create([
            'nama' => ['id' => 'Kegiatan', 'en' => 'Activities'],
            'slug' => ['id' => 'kegiatan', 'en' => 'activities'],
            'urutan' => 1,
            'aktif' => true,
        ]);

        $sebagian = \App\Models\ArticleCategory::query()->find($kategori->id, ['id', 'nama'])->toArray();

        $this->assertSame('Kegiatan', $sebagian['nama']);
        $this->assertArrayNotHasKey('deskripsi', $sebagian);
    }
}
