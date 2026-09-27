<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman publik versi Inggris (berprefiks /en).
 *
 * KENAPA KELAS INI TERPISAH DARI HalamanPublikTest?
 * Paket mcamara/laravel-localization menentukan prefiks bahasa dari URI pada
 * SAAT RUTE DIDAFTARKAN, bukan saat permintaan diproses. Pada pengujian,
 * aplikasi sudah selesai mendaftarkan rute sebelum permintaan HTTP pertama
 * dibuat — sehingga tanpa penanganan khusus seluruh jalur /en menghasilkan 404
 * meski berjalan normal di server sungguhan.
 *
 * Paket menyediakan variabel lingkungan ROUTING_LOCALE tepat untuk keperluan
 * "satu locale per proses" (dipakai juga oleh route caching). Nilai itu
 * dipasang SEBELUM aplikasi dibangun, sehingga rute terdaftar dengan prefiks
 * /en dan jalur /en dapat diuji apa adanya.
 */
class HalamanPublikInggrisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // WAJIB sebelum parent::setUp() — rute didaftarkan saat aplikasi dibangun.
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = 'en';
        $_SERVER['ROUTING_LOCALE'] = 'en';

        parent::setUp();

        // Bersihkan agar tidak memengaruhi kelas uji berikutnya.
        putenv('ROUTING_LOCALE');
        unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);

        $this->seed([PageSeeder::class, SettingSeeder::class, SocialLinkSeeder::class]);
    }

    #[DataProvider('jalurInggris')]
    public function test_halaman_publik_versi_inggris_dapat_dibuka(string $jalur): void
    {
        $this->get($jalur)->assertOk();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function jalurInggris(): array
    {
        $daftar = [
            '/en',
            '/en/sejarah',
            '/en/visi-misi',
            '/en/sambutan',
            '/en/kontak',
            '/en/lokasi',
            '/en/media-sosial',
        ];

        return collect($daftar)
            ->mapWithKeys(fn (string $jalur): array => [$jalur => [$jalur]])
            ->all();
    }

    public function test_halaman_inggris_memakai_bahasa_inggris(): void
    {
        $this->get('/en/kontak')
            ->assertOk()
            ->assertSee(__('umum.kontak.judul', locale: 'en'))
            ->assertSee('name="nama"', false);
    }

    public function test_beranda_inggris_menampilkan_teks_inggris(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee(__('umum.beranda.hero_judul', locale: 'en'));
    }
}
