<?php

namespace Tests\Feature;

use App\Support\Pengaturan;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pintasan ke layar utama (PWA).
 *
 * YANG DIJAGA DI SINI
 *
 * Syarat pemasangan peramban sangat spesifik, dan kegagalannya SENYAP. Kalau
 * satu saja tidak terpenuhi — ikon 512 px tidak ada, `display` salah tulis,
 * alamat manifest salah — peramban hanya berhenti menawarkan pemasangan.
 * Tidak ada galat, tidak ada catatan di log, dan tidak ada yang terlihat
 * rusak. Satu-satunya cara mengetahuinya adalah memeriksa syaratnya satu per
 * satu, dan itulah yang dilakukan berkas ini.
 *
 * APA YANG SENGAJA TIDAK ADA
 *
 * Tidak ada service worker, jadi tidak diuji. Situs ini tidak dirancang untuk
 * dibaca tanpa jaringan; pintasannya murni jalan pintas.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, SettingSeeder::class, SocialLinkSeeder::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return $this->get('/manifest.webmanifest')->assertOk()->json();
    }

    public function test_manifest_memuat_seluruh_syarat_pemasangan(): void
    {
        $manifest = $this->manifest();

        $this->assertNotEmpty($manifest['name'] ?? null, 'name wajib ada.');
        $this->assertNotEmpty($manifest['short_name'] ?? null, 'short_name wajib ada.');
        $this->assertSame('/', $manifest['start_url'] ?? null);

        $this->assertContains(
            $manifest['display'] ?? null,
            ['fullscreen', 'standalone', 'minimal-ui', 'window-controls-overlay'],
            'display menentukan pintasan terbuka tanpa bilah alamat.',
        );

        // Bila ada dan bernilai true, peramban justru diarahkan ke toko
        // aplikasi alih-alih memasang situs ini.
        $this->assertArrayNotHasKey('prefer_related_applications', $manifest);
    }

    public function test_manifest_dikirim_sebagai_manifest_json(): void
    {
        $tipe = $this->get('/manifest.webmanifest')->assertOk()->headers->get('Content-Type');

        $this->assertStringContainsString('manifest+json', (string) $tipe);
    }

    public function test_manifest_menyediakan_ikon_192_512_dan_maskable(): void
    {
        $ikon = collect($this->manifest()['icons'] ?? []);

        $this->assertTrue($ikon->contains(fn (array $i): bool => $i['sizes'] === '192x192'), 'Ikon 192 px wajib ada.');
        $this->assertTrue($ikon->contains(fn (array $i): bool => $i['sizes'] === '512x512'), 'Ikon 512 px wajib ada.');

        // Android memotong ikon sesuai bentuk sistem; tanpa versi maskable,
        // perisainya bisa terpotong.
        $this->assertTrue(
            $ikon->contains(fn (array $i): bool => $i['purpose'] === 'maskable'),
            'Ikon maskable wajib ada untuk Android.',
        );
    }

    public function test_alamat_manifest_tidak_berprefiks_bahasa(): void
    {
        /*
         * Diperiksa dari DAFTAR RUTE, bukan dengan memanggil /en/manifest...
         *
         * Pada pengujian, seluruh alamat /en selalu menjawab 404 karena paket
         * lokalisasi menentukan prefiksnya saat rute didaftarkan. Uji lewat
         * HTTP karena itu akan lulus tanpa membuktikan apa pun.
         */
        $this->assertTrue(Route::has('public.manifest'));

        $this->assertSame(
            'manifest.webmanifest',
            Route::getRoutes()->getByName('public.manifest')->uri(),
            'Manifest harus punya satu alamat tetap, di luar grup lokalisasi.',
        );
    }

    public function test_berkas_ikon_ada_dengan_ukuran_yang_disebut(): void
    {
        $diharapkan = [
            'ikon/ikon-192.png' => [192, 192],
            'ikon/ikon-512.png' => [512, 512],
            'ikon/ikon-maskable-512.png' => [512, 512],
            'ikon/apple-touch-icon.png' => [180, 180],
        ];

        foreach ($diharapkan as $jalur => [$lebar, $tinggi]) {
            $berkas = public_path($jalur);

            $this->assertFileExists($berkas, "Ikon {$jalur} tidak ada.");

            // getimagesize membaca kepala berkas saja dan TIDAK memerlukan
            // ekstensi GD — jadi uji ini berjalan di lingkungan mana pun.
            $ukuran = getimagesize($berkas);

            $this->assertSame($lebar, $ukuran[0], "Lebar {$jalur} tidak sesuai.");
            $this->assertSame($tinggi, $ukuran[1], "Tinggi {$jalur} tidak sesuai.");
            $this->assertSame('image/png', $ukuran['mime'], "Ikon {$jalur} bukan PNG.");
        }
    }

    public function test_setiap_alamat_ikon_di_manifest_menunjuk_berkas_yang_ada(): void
    {
        // Salah tulis satu huruf di sini tidak memunculkan galat apa pun;
        // peramban hanya berhenti menawarkan pemasangan.
        foreach (collect($this->manifest()['icons'] ?? [])->pluck('src') as $alamat) {
            $this->assertFileExists(
                public_path(ltrim((string) $alamat, '/')),
                "Manifest menunjuk ikon yang tidak ada: {$alamat}",
            );
        }
    }

    public function test_nama_di_manifest_mengikuti_pengaturan_situs(): void
    {
        $this->assertSame(Pengaturan::semua()['nama_rayon'], $this->manifest()['name']);
        $this->assertSame(Pengaturan::semua()['nama_singkat'], $this->manifest()['short_name']);
    }

    public function test_halaman_publik_menautkan_manifest_dan_tag_ios(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('/manifest.webmanifest', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $html);
        $this->assertStringContainsString('theme-color', $html);

        // Tombolnya memang ikut dirender — tersembunyi lewat Alpine, tetapi
        // markupnya harus ada. Kalau komponennya salah rujuk, halamannya
        // memang gagal; kalau teksnya yang salah, ini yang menangkapnya.
        $this->assertStringContainsString(__('umum.pasang.label'), $html);
    }

    public function test_tombol_tema_tidak_lagi_menawarkan_ikut_sistem(): void
    {
        /*
         * Tempat tombol ketiga dipakai tombol pasang aplikasi.
         *
         * Diperiksa dari DUA sisi, dan keduanya perlu:
         *  - kuncinya memang sudah tidak ada lagi di berkas bahasa;
         *  - tidak ada tombol yang memanggil pilih('sistem').
         *
         * Memeriksa teks labelnya saja tidak cukup — labelnya hidup di berkas
         * bahasa, dan teks serupa bisa muncul di tempat lain. Percobaan
         * pertama saya justru memeriksa teksnya, dan ketika halamannya
         * menjawab 500, pesan kegagalannya menuduh teksnya masih ada.
         * `assertOk()` di bawah menjaga supaya kegagalan halaman dilaporkan
         * sebagai kegagalan halaman.
         */
        $this->assertArrayNotHasKey('sistem', __('umum.tema'));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString("pilih('terang')", $html);
        $this->assertStringContainsString("pilih('gelap')", $html);
        $this->assertStringNotContainsString("pilih('sistem')", $html);
    }

    public function test_teks_panduan_pasang_lengkap_di_kedua_bahasa(): void
    {
        /*
         * Bahasa Inggris adalah fitur yang gampang tertinggal tanpa ada yang
         * sadar: menambah kunci di lang/id tanpa menambahkannya di lang/en
         * TIDAK memunculkan galat, halamannya hanya menampilkan kunci
         * mentahnya. Uji ini membandingkannya langsung.
         */
        foreach (['id', 'en'] as $bahasa) {
            app()->setLocale($bahasa);

            foreach (['label', 'judul', 'pengantar', 'catatan_ios', 'tutup'] as $kunci) {
                $this->assertNotSame(
                    "umum.pasang.{$kunci}",
                    __("umum.pasang.{$kunci}"),
                    "Teks umum.pasang.{$kunci} belum ada untuk bahasa {$bahasa}.",
                );
            }

            $this->assertCount(3, __('umum.pasang.langkah'), "Langkah panduan iOS bahasa {$bahasa} tidak lengkap.");
        }
    }
}
