<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Services\Redaksi;
use Database\Seeders\PageSeeder;
use Database\Seeders\PeriodSeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gambar pratinjau tautan (og:image).
 *
 * YANG DIJAGA DI SINI
 *
 * og:image adalah bagian yang TIDAK PERNAH terlihat oleh pengurus: ia hanya
 * muncul saat tautannya dibagikan ke WhatsApp, Instagram, atau Facebook. Kalau
 * alamatnya salah — salah ketik, berkasnya tidak ada, atau halamannya lupa
 * menentukan gambarnya — tidak ada satu pun galat yang muncul di log. Yang
 * terjadi hanyalah pratinjau tautan yang kosong, dan itu tidak pernah
 * dilaporkan siapa pun.
 *
 * Karena itu yang diperiksa di sini bukan "apakah ada berkas di folder", tetapi
 * "alamat apa yang BENAR-BENAR dikirim ke pengikis tautan" — dan apakah berkas
 * di alamat itu memang ada.
 */
class GambarPratinjauTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1×1 piksel, supaya uji tidak bergantung pada ekstensi GD. */
    private const PNG_KECIL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
            SocialLinkSeeder::class,
            UnitSeeder::class,
            PeriodSeeder::class,
            PositionSeeder::class,
        ]);
    }

    /**
     * Halaman publik dan gambar yang seharusnya dipakainya.
     *
     * Perhatikan `/visi-misi`: KUNCI halamannya adalah `visi_misi` (garis
     * bawah) sedangkan berkasnya `visi-misi.png` (tanda hubung). Memetakannya
     * dengan menerka nama dari kunci akan menghasilkan alamat yang tidak ada,
     * dan kegagalannya senyap — karena itu pasangan ini diuji, bukan
     * diasumsikan.
     *
     * @return array<string, string>
     */
    private function harapan(): array
    {
        return [
            '/' => 'og/beranda.png',
            '/sejarah' => 'og/sejarah.png',
            '/visi-misi' => 'og/visi-misi.png',
            '/sambutan' => 'og/sambutan.png',
            '/struktur' => 'og/struktur.png',
            '/lso' => 'og/lso.png',
            '/anggota' => 'og/anggota.png',
            '/alumni' => 'og/alumni.png',
            '/publikasi' => 'og/publikasi.png',
            '/prestasi' => 'og/prestasi.png',
            '/kontak' => 'og/kontak.png',
            '/lokasi' => 'og/lokasi.png',
            '/media-sosial' => 'og/media-sosial.png',
            '/aspirasi' => 'og/aspirasi.png',
            '/pengumuman' => 'og/pengumuman.png',
            '/arsip' => 'og/arsip.png',
            '/inventaris' => 'og/inventaris.png',
            '/perpustakaan' => 'og/perpustakaan.png',
            '/pendaftaran' => 'og/pendaftaran.png',
        ];
    }

    /**
     * Ambil alamat og:image dari HTML halaman.
     */
    private function ogImage(string $html): string
    {
        preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $cocok);

        return $cocok[1] ?? '';
    }

    public function test_setiap_halaman_publik_memakai_gambar_pratinjau_yang_benar(): void
    {
        foreach ($this->harapan() as $alamat => $berkas) {
            $html = $this->get($alamat)->assertOk()->getContent();

            $this->assertSame(
                asset($berkas),
                $this->ogImage($html),
                "Halaman {$alamat} tidak memakai {$berkas} sebagai gambar pratinjaunya.",
            );

            // Alamat yang benar tetapi berkasnya tidak ada tetap menghasilkan
            // pratinjau kosong — jadi keduanya diperiksa berdampingan.
            $this->assertFileExists(public_path($berkas), "Berkas {$berkas} tidak ada.");
        }
    }

    public function test_alamat_gambar_pratinjau_selalu_lengkap(): void
    {
        /*
         * WhatsApp dan Facebook TIDAK menjalankan kode kita. Alamat relatif
         * tidak bisa mereka selesaikan, dan kegagalannya berupa pratinjau
         * kosong tanpa galat apa pun di sisi kita.
         */
        foreach (array_keys($this->harapan()) as $alamat) {
            $gambar = $this->ogImage($this->get($alamat)->assertOk()->getContent());

            $this->assertStringStartsWith('http', $gambar, "Gambar pratinjau {$alamat} bukan alamat lengkap.");
        }
    }

    public function test_semua_gambar_pratinjau_berukuran_1200x630(): void
    {
        $berkas = glob(public_path('og/*.png')) ?: [];

        $this->assertGreaterThanOrEqual(20, count($berkas), 'Gambar pratinjau per halaman belum lengkap.');

        foreach ($berkas as $satu) {
            // getimagesize membaca kepala berkas saja dan tidak butuh ekstensi
            // GD, jadi uji ini berjalan di lingkungan mana pun.
            $ukuran = getimagesize($satu);

            $this->assertSame(1200, $ukuran[0], basename($satu).' tidak selebar 1200 px.');
            $this->assertSame(630, $ukuran[1], basename($satu).' tidak setinggi 630 px.');
            $this->assertSame('image/png', $ukuran['mime'], basename($satu).' bukan PNG.');
        }
    }

    public function test_artikel_memakai_gambar_covernya_sendiri(): void
    {
        /*
         * Inilah bagian "dinamis": setiap artikel membawa pratinjaunya sendiri,
         * sehingga tautan yang dibagikan menampilkan isi tulisannya — bukan
         * gambar halaman publikasi yang sama untuk semuanya.
         */
        $penulis = $this->pengurus('konten_manager');

        $media = MediaLibrary::induk()
            ->addMedia(UploadedFile::fake()->createWithContent('sampul.png', base64_decode(self::PNG_KECIL)))
            ->toMediaCollection('gambar');

        $artikel = $this->artikel($penulis, ['cover_media_id' => $media->id]);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        $alamat = '/publikasi/'.$artikel->tipe.'/'.$artikel->getTranslation('slug', 'id');
        $html = $this->get($alamat)->assertOk()->getContent();

        $this->assertStringContainsString($media->getUrl(), $this->ogImage($html));
        $this->assertStringNotContainsString(asset('og/publikasi.png'), $this->ogImage($html));
    }

    public function test_artikel_tanpa_cover_memakai_gambar_bawaan_publikasi(): void
    {
        $penulis = $this->pengurus('konten_manager');
        $artikel = $this->artikel($penulis);
        app(Redaksi::class)->terbitkan($artikel, $penulis);

        $html = $this->get('/publikasi/'.$artikel->tipe.'/'.$artikel->getTranslation('slug', 'id'))
            ->assertOk()
            ->getContent();

        $this->assertSame(asset('og/publikasi.png'), $this->ogImage($html));
    }

    public function test_tombol_pasang_dirender_di_bilah_atas_untuk_ponsel(): void
    {
        /*
         * Tiga tempat, dan ketiganya perlu:
         *  1. bilah atas ponsel — inilah yang membuatnya terlihat sama sekali;
         *  2. grup tema di layar lebar;
         *  3. grup tema di dalam laci menu ponsel.
         *
         * Sebelum ini hanya ada (2) dan (3), sehingga di ponsel tombolnya
         * praktis tidak pernah terlihat: grup tema baru muncul setelah laci
         * dibuka.
         */
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            3,
            substr_count($html, 'x-data="pasangAplikasi"'),
            'Tombol pasang seharusnya dirender di tiga tempat.',
        );
    }

    private function pengurus(string $peran): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function artikel(User $penulis, array $ganti = []): Article
    {
        return app(Redaksi::class)->simpan(new Article, array_merge([
            'tipe' => Article::TIPE_BERITA,
            'judul' => ['id' => 'Mapaba 2026 Berlangsung Meriah', 'en' => ''],
            'ringkasan' => ['id' => 'Kegiatan Mapaba tahun ini diikuti 120 peserta baru.', 'en' => ''],
            'konten' => ['id' => '<p>'.str_repeat('Mapaba berlangsung dengan penuh semangat. ', 6).'</p>', 'en' => ''],
            'tag' => 'mapaba, kaderisasi',
        ], $ganti), $penulis);
    }
}
