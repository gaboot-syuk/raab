<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Area panel dijaga oleh autentikasi, verifikasi email, dan izin per peran.
 */
class AksesPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, SettingSeeder::class, PageSeeder::class]);
    }

    private function pengguna(string $peran): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($peran);

        return $user;
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get('/panel')->assertRedirect('/login');
    }

    public function test_pengguna_tanpa_verifikasi_email_tidak_dapat_masuk_panel(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // Fortify mendaftarkan rute verifikasi pada /email/verify.
        $this->actingAs($user)->get('/panel')->assertRedirect('/email/verify');
    }

    public function test_superadmin_dapat_membuka_seluruh_halaman_panel(): void
    {
        $superadmin = $this->pengguna('superadmin');

        $halaman = [
            '/panel',
            '/panel/pesan',
            '/panel/halaman',
            '/panel/slider',
            '/panel/media',
            '/panel/pengaturan',
            '/panel/pengguna',
            '/panel/peran',
        ];

        foreach ($halaman as $jalur) {
            $this->actingAs($superadmin)->get($jalur)->assertOk();
        }
    }

    public function test_pengguna_tanpa_peran_tidak_dapat_membuka_pengelolaan_akun(): void
    {
        $biasa = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($biasa)->get('/panel/pengguna')->assertForbidden();
        $this->actingAs($biasa)->get('/panel/peran')->assertForbidden();
    }

    public function test_konten_manager_tidak_dapat_membuka_pengelolaan_akun(): void
    {
        $konten = $this->pengguna('konten_manager');

        // Boleh mengelola konten, tetapi bukan akun pengurus.
        $this->actingAs($konten)->get('/panel/halaman')->assertOk();
        $this->actingAs($konten)->get('/panel/pengguna')->assertForbidden();
    }

    public function test_superadmin_tidak_dapat_menghapus_akunnya_sendiri(): void
    {
        $superadmin = $this->pengguna('superadmin');

        $this->actingAs($superadmin)
            ->delete("/panel/pengguna/{$superadmin->id}")
            ->assertSessionHas('galat');

        $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
    }

    public function test_superadmin_tidak_dapat_menanggalkan_peran_superadmin_miliknya(): void
    {
        $superadmin = $this->pengguna('superadmin');

        $this->actingAs($superadmin)
            ->put("/panel/pengguna/{$superadmin->id}", [
                'name' => $superadmin->name,
                'email' => $superadmin->email,
                'peran' => ['sekretaris'],
                'verifikasi_email' => true,
            ])
            ->assertSessionHas('galat');

        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
    }

    public function test_peran_superadmin_tidak_dapat_diubah_izinnya(): void
    {
        $superadmin = $this->pengguna('superadmin');
        $peran = \Spatie\Permission\Models\Role::query()->where('name', 'superadmin')->firstOrFail();

        $this->actingAs($superadmin)
            ->put("/panel/peran/{$peran->id}", ['izin' => []])
            ->assertSessionHas('galat');

        $this->assertGreaterThan(0, $peran->fresh()->permissions()->count());
    }
}
