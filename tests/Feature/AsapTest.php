<?php

namespace Tests\Feature;

use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji asap (smoke test): aplikasi menyala dan jalur penting merespons.
 *
 * Beranda membaca pengaturan situs & tautan media sosial dari basis data,
 * karena itu basis data disiapkan lebih dulu.
 */
class AsapTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_merespons_200_pada_basis_data_minimal(): void
    {
        // Tanpa satu pun pengaturan diisi, beranda tetap harus tampil
        // memakai nilai bawaan — bukan menampilkan galat 500.
        $this->seed([SettingSeeder::class, SocialLinkSeeder::class]);

        $this->get('/')->assertOk();
    }

    public function test_jalur_kesehatan_merespons_200(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_halaman_panel_mengalihkan_tamu_ke_masuk(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }
}
