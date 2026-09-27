<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\DemoPeranSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data demo untuk mencoba aplikasi.
 *
 * Uji ini bukan menguji "seeder berjalan". Yang diuji adalah JANJI yang
 * dipegang seeder itu: setiap peran benar-benar bisa masuk dan membuka
 * dasbornya. Kalau janji itu tidak benar, demo akan berhenti di layar masuk,
 * dan yang tampak adalah aplikasi yang rusak — padahal yang salah cuma datanya.
 */
class DemoPeranSeederTest extends TestCase
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

    private function jalankanSeedDemo(): void
    {
        $this->seed(DemoPeranSeeder::class);
    }

    /**
     * Akun anggota demo dibuat dari anggota yang SUDAH ada.
     *
     * Seeder tidak membuat anggota baru dari nol: anggota tanpa iuran, poin,
     * dan peminjaman hanya menghasilkan dashboard kosong, dan dashboard kosong
     * tidak membuktikan apa pun.
     */
    private function buatAnggota(string $status): Member
    {
        $user = User::factory()->create([
            'email' => 'anggota-'.$status.'@contoh.test',
            'email_verified_at' => now(),
        ]);

        return Member::query()->create([
            'user_id' => $user->id,
            'nomor_anggota' => 'RAAB-'.$status.'-001',
            'slug' => 'anggota-'.$status,
            'jalur' => $status === Member::STATUS_ALUMNI ? Member::JALUR_ALUMNI : Member::JALUR_KADER,
            'status' => $status,
            'nama_lengkap' => 'Anggota '.ucfirst($status),
            'jenis_kelamin' => 'laki_laki',
            'tempat_lahir' => 'Surakarta',
            'tanggal_lahir' => '2002-01-01',
            'nim' => '2211'.$status,
            'fakultas' => 'Ushuluddin dan Dakwah',
            'program_studi' => 'Komunikasi dan Penyiaran Islam',
            'angkatan' => 2022,
            'alamat' => 'Sukoharjo, Jawa Tengah',
            'telepon' => '081234567890',
        ]);
    }

    public function test_seeder_membuat_akun_untuk_setiap_peran(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        foreach (['superadmin', 'sekretaris', 'bendahara', 'konten_manager'] as $peran) {
            $this->assertGreaterThan(
                0,
                User::query()->role($peran)->count(),
                "Tidak ada satu pun akun dengan peran {$peran} — dashboard peran itu tidak bisa dicoba."
            );
        }
    }

    public function test_kata_sandi_akun_demo_benar_benar_berlaku(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        $email = ['ketua@raab.test', 'sekretaris@raab.test', 'bendahara@raab.test', 'konten@raab.test', 'kader@raab.test', 'alumni@raab.test'];

        foreach ($email as $satu) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Auth::validate([
                    'email' => $satu,
                    'password' => DemoPeranSeeder::KATA_SANDI,
                ]),
                "Akun {$satu} tidak bisa masuk dengan kata sandi demo."
            );
        }
    }

    /**
     * Email harus terverifikasi.
     *
     * Panel pengurus memakai middleware `verified`. Akun demo yang belum
     * terverifikasi akan berhenti di layar "verifikasi email dulu" — dan demo
     * yang berhenti di tengah bukan demo.
     */
    public function test_akun_demo_sudah_terverifikasi(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        foreach (User::query()->whereIn('email', ['sekretaris@raab.test', 'bendahara@raab.test', 'konten@raab.test'])->get() as $user) {
            $this->assertNotNull($user->email_verified_at, "Email {$user->email} belum terverifikasi.");
        }
    }

    public function test_setiap_peran_dapat_membuka_dasbor_panelnya(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        foreach (['ketua@raab.test', 'sekretaris@raab.test', 'bendahara@raab.test', 'konten@raab.test'] as $email) {
            $this->actingAs(User::query()->where('email', $email)->firstOrFail())
                ->get('/panel')
                ->assertOk();
        }
    }

    public function test_kader_dan_alumni_dapat_membuka_area_anggota(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        foreach (['kader@raab.test', 'alumni@raab.test'] as $email) {
            $this->actingAs(User::query()->where('email', $email)->firstOrFail())
                ->get('/dasbor')
                ->assertOk();
        }
    }

    /**
     * Seeder ini menimpa kata sandi akun. Kalau ia pernah berjalan di server
     * sungguhan, seluruh pengurus bisa terkunci dari akunnya sendiri.
     */
    public function test_seeder_menolak_berjalan_di_produksi(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $sebelum = User::query()->count();

        // --force dipakai sengaja: pertanyaan "aplikasi berjalan di produksi,
        // yakin?" dari Laravel dilewati, sehingga yang benar-benar diuji adalah
        // penjaga DI DALAM seeder, bukan konfirmasi bawaannya.
        $this->artisan('db:seed', ['--class' => DemoPeranSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertSame($sebelum, User::query()->count(), 'Seeder demo berjalan di produksi.');
        $this->assertSame(0, User::query()->where('email', 'sekretaris@raab.test')->count());
    }

    public function test_seeder_aman_dijalankan_dua_kali(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();
        $pertama = [
            'pengguna' => User::query()->count(),
            'slider' => \App\Models\Slider::query()->count(),
            'galeri' => \App\Models\Gallery::query()->count(),
            'kategori_prestasi' => \App\Models\AchievementCategory::query()->count(),
        ];

        $this->jalankanSeedDemo();

        $this->assertSame($pertama['pengguna'], User::query()->count());
        $this->assertSame($pertama['slider'], \App\Models\Slider::query()->count());
        $this->assertSame($pertama['galeri'], \App\Models\Gallery::query()->count());
        $this->assertSame($pertama['kategori_prestasi'], \App\Models\AchievementCategory::query()->count());
    }

    public function test_berkas_contoh_galeri_benar_benar_ada(): void
    {
        $this->buatAnggota(Member::STATUS_AKTIF);
        $this->buatAnggota(Member::STATUS_ALUMNI);

        $this->jalankanSeedDemo();

        $item = \App\Models\GalleryItem::query()->firstOrFail();

        // Galeri berisi gambar yang tidak ada hanya akan tampil sebagai kotak
        // rusak — dan kotak rusak tidak membuktikan galerinya bekerja.
        $this->assertStringStartsWith('/storage/demo/', (string) $item->url);
        $this->assertFileExists(storage_path('app/public/demo/'.basename((string) $item->url)));
    }
}
