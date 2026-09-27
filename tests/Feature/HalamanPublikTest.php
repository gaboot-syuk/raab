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
