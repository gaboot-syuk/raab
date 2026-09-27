<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\Redaksi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Fase 9C — keterlihatan di mesin pencari.
 *
 * Yang dijaga di sini BUKAN "ada tag meta", melainkan dua hal yang mudah salah
 * dan mahal akibatnya:
 *
 *  1. Peta situs dan data terstruktur hanya boleh memuat yang SUDAH TERBIT.
 *     Memasukkan draf berarti mengundang mesin pencari mengindeks naskah yang
 *     belum selesai — bahkan yang sengaja belum dipublikasikan.
 *
 *  2. Halaman privat tidak boleh muncul di peta situs. Peta situs adalah
 *     undangan; mencantumkan /panel atau /anggota sama dengan mengundang
 *     pengindeksan ke pintu yang seharusnya tertutup.
 */
class SeoTest extends TestCase
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
            'tag' => 'mapaba, kaderisasi',
        ], $ganti);
    }

    private function artikelTerbit(array $ganti = []): Article
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = app(Redaksi::class)->simpan(new Article, $this->data($ganti), $penulis);

        return app(Redaksi::class)->terbitkan($artikel, $penulis);
    }

    /* ------------------------------------------------------------------ */
    /* Peta situs                                                          */
    /* ------------------------------------------------------------------ */

    public function test_peta_situs_tersedia_sebagai_xml(): void
    {
        $respons = $this->get('/sitemap.xml');

        $respons->assertOk();
        $this->assertStringContainsString('xml', (string) $respons->headers->get('content-type'));

        $isi = $respons->getContent();
        $this->assertStringContainsString('<urlset', $isi);
        $this->assertStringContainsString('http://www.sitemaps.org/schemas/sitemap/0.9', $isi);
        $this->assertStringContainsString('<loc>', $isi);
    }

    /**
     * Peta situs bukan halaman untuk dibaca manusia.
     *
     * Kalau ia terindeks, hasil pencarian bisa berisi daftar tautan mentah —
     * halaman yang isinya hanya alamat, tanpa satu pun penjelasan.
     */
    public function test_peta_situs_ditandai_agar_tidak_diindeks(): void
    {
        $respons = $this->get('/sitemap.xml');

        $this->assertStringContainsString('noindex', (string) $respons->headers->get('X-Robots-Tag'));
    }

    public function test_peta_situs_memuat_versi_bahasa_lain(): void
    {
        $isi = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('xhtml:link', $isi);
        $this->assertStringContainsString('hreflang="id"', $isi);
        $this->assertStringContainsString('hreflang="en"', $isi);
    }

    /**
     * Peta situs harus satu alamat saja.
     *
     * Selama rutenya berada di dalam grup berprefiks bahasa, tersedia dua peta
     * situs yang isinya identik (/sitemap.xml dan /en/sitemap.xml). Bagi mesin
     * pencari itu dua berkas berbeda — bukan satu berkas dengan dua bahasa.
     */
    public function test_hanya_ada_satu_alamat_peta_situs(): void
    {
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/en/sitemap.xml')->assertNotFound();
    }

    /**
     * Jalur area anggota TIDAK berawalan /anggota.
     *
     * /anggota justru halaman PUBLIK: direktori kader yang profilnya dibuka.
     * Area anggota berada di /dasbor, /profil, /kartu-kader, dan seterusnya —
     * dan itulah yang tidak boleh masuk peta situs.
     */
    public function test_peta_situs_tidak_memuat_halaman_privat(): void
    {
        $isi = $this->get('/sitemap.xml')->getContent();

        foreach (['/panel', '/dasbor', '/profil', '/kartu-kader', '/pustaka', '/notifikasi', '/login', '/storage/', '/_ignition'] as $jalur) {
            $this->assertStringNotContainsString(
                '<loc>'.$this->dasar().$jalur,
                $isi,
                "Peta situs mencantumkan {$jalur} — mengundang pengindeksan ke pintu yang seharusnya tertutup."
            );
        }

        /*
         * /arsip (halaman daftar) memang publik dan boleh ada di peta situs.
         * Yang tidak boleh adalah tautan UNDUHANNYA: alamat unduh memuat token
         * akses, dan menautkannya ke indeks berarti membocorkan tautan itu.
         */
        $this->assertStringContainsString('<loc>'.$this->dasar().'/arsip</loc>', $isi);
        $this->assertStringNotContainsString('/unduh', $isi);
    }

    public function test_peta_situs_hanya_memuat_artikel_yang_sudah_terbit(): void
    {
        $terbit = $this->artikelTerbit();

        // Draf: dibuat, tidak diterbitkan.
        $penulis = $this->pengurus('konten_manager');
        $draf = app(Redaksi::class)->simpan(new Article, $this->data([
            'judul' => ['id' => 'Naskah Draf Yang Belum Selesai', 'en' => ''],
        ]), $penulis);

        $isi = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString($terbit->getTranslation('slug', 'id', false), $isi);
        $this->assertStringNotContainsString(
            $draf->getTranslation('slug', 'id', false),
            $isi,
            'Naskah yang belum terbit ikut diundang ke mesin pencari.'
        );
    }

    /**
     * Alamat di peta situs harus SAMA dari mana pun diminta.
     *
     * Peta situs di-cache, dan cache-nya tidak menyimpan dari mana permintaan
     * datang. Kalau alamatnya mengikuti permintaan, kunjungan PERTAMA yang
     * menentukan alamat untuk semua orang selama enam jam berikutnya — satu
     * perayap lewat nama staging akan membuat peta situs mengumumkan alamat itu
     * ke seluruh mesin pencari.
     */
    public function test_alamat_peta_situs_tidak_berubah_mengikuti_permintaan(): void
    {
        $dariSitus = $this->get('/sitemap.xml')->getContent();

        // Cache dibuang, lalu diminta lewat alamat yang berbeda.
        Cache::forget('sitemap.xml');
        $dariAlamatLain = $this->get('http://staging.contoh.test/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('staging.contoh.test', $dariAlamatLain);
        $this->assertSame($dariSitus, $dariAlamatLain);
    }

    private function dasar(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /* ------------------------------------------------------------------ */
    /* robots.txt                                                          */
    /* ------------------------------------------------------------------ */

    public function test_robots_txt_menutup_panel_anggota_dan_berkas_privat(): void
    {
        $berkas = public_path('robots.txt');

        $this->assertFileExists($berkas);

        $isi = File::get($berkas);

        foreach (['/panel', '/dasbor', '/profil', '/kartu-kader', '/arsip/', '/storage/', '/login'] as $jalur) {
            $this->assertStringContainsString(
                'Disallow: '.$jalur,
                $isi,
                "robots.txt tidak menutup {$jalur}."
            );
        }

        $this->assertStringContainsString('Sitemap:', $isi);
        $this->assertStringContainsString('/sitemap.xml', $isi);
    }

    /* ------------------------------------------------------------------ */
    /* Meta halaman                                                        */
    /* ------------------------------------------------------------------ */

    public function test_halaman_artikel_memakai_og_type_article_sebanyak_sekali(): void
    {
        $artikel = $this->artikelTerbit();

        $html = $this->get('/publikasi/'.$artikel->tipe.'/'.$artikel->getTranslation('slug', 'id', false))->getContent();

        // Dua tag og:type membuat mesin pencari membaca dua nilai berbeda untuk
        // satu halaman yang sama — dan yang dipakai tidak bisa ditebak.
        $this->assertSame(
            1,
            substr_count($html, 'property="og:type"'),
            'og:type ditulis lebih dari sekali.'
        );
        $this->assertStringContainsString('property="og:type" content="article"', $html);
    }

    public function test_halaman_artikel_memuat_data_terstruktur_newsarticle(): void
    {
        $artikel = $this->artikelTerbit();

        $html = $this->get('/publikasi/'.$artikel->tipe.'/'.$artikel->getTranslation('slug', 'id', false))->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"NewsArticle"', $html);
        $this->assertStringContainsString('"headline"', $html);
        $this->assertStringContainsString('"datePublished"', $html);
        $this->assertStringContainsString('"publisher"', $html);
    }

    public function test_judul_seo_khusus_dipakai_bila_diisi(): void
    {
        $artikel = $this->artikelTerbit();

        // Judul SEO diisi setelah terbit, lalu halaman dibuka lagi.
        $artikel->setTranslation('seo_judul', 'id', 'Judul Khusus Untuk Mesin Pencari');
        $artikel->save();

        $html = $this->get('/publikasi/'.$artikel->tipe.'/'.$artikel->getTranslation('slug', 'id', false))->getContent();

        $this->assertStringContainsString('Judul Khusus Untuk Mesin Pencari', $html);
    }

    public function test_halaman_publik_memuat_kanonik_dan_bahasa_alternatif(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('hreflang="id"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
    }

    public function test_halaman_publik_memuat_data_terstruktur_organisasi(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('"@type":"Organization"', $html);
    }
}
