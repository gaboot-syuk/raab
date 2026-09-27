<?php

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman publik versi Indonesia (tanpa prefiks bahasa).
 *
 * Halaman publik dirender server-side (Blade) sehingga harus tetap dapat dibuka
 * tanpa JavaScript.
 *
 * Versi Inggris diuji terpisah pada HalamanPublikInggrisTest: paket
 * mcamara/laravel-localization menentukan prefiks bahasa pada saat rute
 * didaftarkan, sehingga kelas uji berprefiks /en perlu perlakuan tersendiri.
 */
class HalamanPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, SettingSeeder::class, SocialLinkSeeder::class]);
    }

    public function test_seluruh_halaman_publik_indonesia_dapat_dibuka(): void
    {
        $this->assertSemuaTerbuka([
            '/',
            '/sejarah',
            '/visi-misi',
            '/sambutan',
            '/kontak',
            '/lokasi',
            '/media-sosial',
        ]);
    }

    public function test_beranda_menampilkan_identitas_dari_pengaturan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('PMII RAAB', false);
    }

    public function test_beranda_memakai_tampilan_bawaan_sebelum_ada_slider(): void
    {
        // Tanpa slider di basis data, beranda harus tetap menampilkan hero bawaan.
        $this->get('/')
            ->assertOk()
            ->assertSee(__('umum.beranda.hero_judul'));
    }

    public function test_halaman_statis_yang_tidak_ada_menghasilkan_404(): void
    {
        // Kunci halaman dihapus → rute tetap ada, tetapi isinya tidak ditemukan.
        Page::query()->where('kunci', 'sejarah')->delete();

        $this->get('/sejarah')->assertNotFound();
    }

    public function test_halaman_kontak_memuat_formulir(): void
    {
        $this->get('/kontak')
            ->assertOk()
            ->assertSee('name="nama"', false)
            ->assertSee('name="pesan"', false);
    }

    public function test_halaman_lokasi_memuat_alamat_dan_jam_operasional(): void
    {
        $this->get('/lokasi')
            ->assertOk()
            ->assertSee(__('umum.lokasi.alamat'))
            ->assertSee(__('umum.lokasi.jam'));
    }

    public function test_halaman_media_sosial_menampilkan_seluruh_platform(): void
    {
        $this->get('/media-sosial')
            ->assertOk()
            ->assertSee('Instagram')
            ->assertSee('YouTube');
    }

    public function test_footer_menampilkan_kontak_dari_pengaturan_situs(): void
    {
        // Footer mengambil email & nomor dari pengaturan, bukan dari kode.
        $this->get('/')
            ->assertOk()
            ->assertSee('sekretariat@raab.test', false);
    }

    /**
     * Pengalih bahasa harus tersedia untuk kedua bahasa.
     *
     * CATATAN: isi atribut href TIDAK diperiksa di sini. Paket lokalisasi
     * menyimpan objek Request saat layanannya pertama dibuat, sedangkan pada
     * pengujian aplikasi sudah dibangun sebelum permintaan HTTP ada — sehingga
     * URL yang dihasilkan selalu mengarah ke akar situs. Perilaku ini hanya
     * terjadi pada pengujian; pada server sungguhan pengalih bahasa tetap
     * mempertahankan halaman yang sedang dibuka (sudah diperiksa langsung di
     * peramban).
     */
    public function test_pengalih_bahasa_tersedia_untuk_kedua_bahasa(): void
    {
        $isi = (string) $this->get('/kontak')->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="id"', $isi);
        $this->assertStringContainsString('hreflang="en"', $isi);

        // Label tombol bahasa: ID dan EN.
        $this->assertMatchesRegularExpression('~>\s*ID\s*</a>~', $isi);
        $this->assertMatchesRegularExpression('~>\s*EN\s*</a>~', $isi);
    }

    /**
     * Tombol pendaftaran harus benar-benar tampil, bukan sekadar tidak error.
     *
     * Ketiganya dulu memanggil rute `public.pendaftaran.mapaba` yang tidak
     * pernah didaftarkan. Karena semuanya dibungkus `Route::has()`, kegagalannya
     * senyap: tombolnya hilang tanpa galat apa pun — halaman tetap 200 dan uji
     * lain tetap hijau. Nama rute yang benar adalah `public.pendaftaran` untuk
     * daftar kegiatan, dan `public.pendaftaran.jenis` untuk pintasan per jenis.
     */
    public function test_tombol_pendaftaran_tampil_dan_mengarah_ke_rute_yang_benar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.route('public.pendaftaran').'"', false)
            ->assertSee('href="'.route('public.pendaftaran.jenis', ['jenis' => 'mapaba']).'"', false)
            ->assertSee('href="'.route('public.pendaftaran.jenis', ['jenis' => 'pkd']).'"', false)
            ->assertSee(__('umum.tombol.daftar_sekarang'))
            ->assertSee(__('umum.submenu.mapaba'))
            ->assertSee(__('umum.submenu.pkd'));
    }

    /**
     * Setiap butir navigasi bawah harus berupa tautan yang dapat diklik.
     *
     * Butir yang rutenya tidak dikenali dirender sebagai <span> abu-abu,
     * sehingga pengguna melihat ikon yang tidak bisa ditekan tanpa penjelasan
     * apa pun.
     */
    public function test_navigasi_bawah_sepenuhnya_berupa_tautan(): void
    {
        $isi = (string) $this->get('/')->assertOk()->getContent();

        preg_match('~<nav[^>]*bottom-0[^>]*>(.*?)</nav>~s', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Navigasi bawah tidak ditemukan pada beranda.');

        $navigasi = $cocok[1];

        $this->assertStringNotContainsString(
            'text-muted',
            $navigasi,
            'Ada butir navigasi bawah yang dirender sebagai teks mati, bukan tautan.',
        );
        // Dihitung dengan pola, BUKAN `substr_count($navigasi, '<a ')`:
        // atributnya ditulis bersambung ke baris berikutnya, sehingga rangkaian
        // "<a " tidak pernah muncul dan hitungannya selalu nol.
        $this->assertSame(
            5,
            preg_match_all('~<a\b~', $navigasi),
            'Navigasi bawah seharusnya memuat lima tautan aktif.',
        );
    }

    /**
     * Footer memakai dua kolom pada layar kecil.
     *
     * Sebelumnya footer menumpuk menjadi satu kolom sehingga memanjang jauh ke
     * bawah. Dua bagian terlebar (Tentang dan Kontak) dibiarkan membentang
     * penuh supaya teksnya tidak terpotong.
     */
    public function test_footer_memakai_grid_dua_kolom_pada_layar_kecil(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('grid grid-cols-2 gap-x-4 gap-y-8 lg:grid-cols-4', false)
            ->assertSee('col-span-2 lg:col-span-1', false);
    }

    /**
     * Setiap butir dropdown navigasi harus berupa tautan yang dapat diklik.
     *
     * Kelima butir Publikasi dulu memanggil rute `public.publikasi.berita` dan
     * kawan-kawan — nama yang tidak pernah didaftarkan. Karena semuanya
     * dibungkus `Route::has()`, seluruh dropdown menjadi teks mati: terlihat,
     * tetapi tidak bisa diklik, tanpa satu pun galat.
     */
    public function test_dropdown_navigasi_berisi_tautan_yang_dapat_diklik(): void
    {
        $isi = (string) $this->get('/')->assertOk()->getContent();

        foreach (['berita', 'opini', 'kajian', 'esai', 'sastra'] as $tipe) {
            $this->assertStringContainsString(
                'href="'.route('public.publikasi.tipe', ['tipe' => $tipe]).'"',
                $isi,
                'Dropdown Publikasi kehilangan tautan untuk tipe '.$tipe.'.',
            );
        }

        // Menu galeri ikut ditawarkan di dalam dropdown Publikasi.
        $this->assertStringContainsString('href="'.route('public.galeri').'"', $isi);
    }

    /**
     * Periksa seluruh jalur sekaligus, lalu laporkan SEMUA yang gagal —
     * bukan hanya yang pertama — supaya perbaikan bisa dilakukan sekali jalan.
     *
     * @param  array<int, string>  $daftar
     */
    private function assertSemuaTerbuka(array $daftar): void
    {
        $gagal = [];

        foreach ($daftar as $jalur) {
            $status = $this->get($jalur)->getStatusCode();

            if ($status !== 200) {
                $gagal[$jalur] = $status;
            }
        }

        $this->assertSame([], $gagal, 'Jalur berikut tidak mengembalikan 200: '.json_encode($gagal));
    }
}
