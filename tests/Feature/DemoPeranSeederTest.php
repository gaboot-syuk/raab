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
     * Menyetel variabel lingkungan seperti hosting sungguhan.
     *
     * putenv() saja TIDAK cukup. Laravel membaca lewat beberapa adaptor
     * ($_SERVER, $_ENV, getenv) dan yang ditemukan lebih dulu itulah yang
     * menang. Uji yang hanya memakai putenv() bisa lolos di satu mesin dan
     * gagal di mesin lain — dan gagalnya menyesatkan: seeder tampak menolak
     * berjalan, padahal yang salah adalah cara ujinya menyetel nilai.
     *
     * @param  array<string, string>  $nilai
     * @return array<string, string|null>  Nilai asli, untuk dipulihkan.
     */
    private function setelEnv(array $nilai): array
    {
        $asli = [];

        foreach ($nilai as $kunci => $isi) {
            $lama = getenv($kunci);
            $asli[$kunci] = $_SERVER[$kunci] ?? $_ENV[$kunci] ?? ($lama === false ? null : $lama);

            putenv("{$kunci}={$isi}");
            $_ENV[$kunci] = $isi;
            $_SERVER[$kunci] = $isi;
        }

        return $asli;
    }

    /**
     * @param  array<string, string|null>  $asli
     */
    private function pulihkanEnv(array $asli): void
    {
        foreach ($asli as $kunci => $isi) {
            if ($isi === null) {
                putenv($kunci);
                unset($_ENV[$kunci], $_SERVER[$kunci]);

                continue;
            }

            putenv("{$kunci}={$isi}");
            $_ENV[$kunci] = $isi;
            $_SERVER[$kunci] = $isi;
        }
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
     * Seeder ini pernah MENIMPA kata sandi akun yang sudah ada. Kalau ia
     * berjalan diam-diam di server sungguhan, seluruh pengurus bisa terkunci
     * dari akunnya sendiri — dan kata sandinya kembali ke nilai yang tertulis
     * di repositori.
     *
     * Penimpaan itu sekarang dihapus. Yang tersisa adalah penjaganya: tanpa
     * sakelar APP_JALANKAN_SEED_DEMO=true, produksi tidak dijamah sama sekali.
     */
    public function test_seeder_menolak_berjalan_di_produksi_tanpa_sakelar(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $sebelum = User::query()->count();

        // --force dipakai sengaja: pertanyaan "aplikasi berjalan di produksi,
        // yakin?" dari Laravel dilewati, sehingga yang benar-benar diuji adalah
        // penjaga DI DALAM seeder, bukan konfirmasi bawaannya.
        $this->artisan('db:seed', ['--class' => DemoPeranSeeder::class, '--force' => true])
            ->assertSuccessful();

        $this->assertSame($sebelum, User::query()->count(), 'Seeder demo berjalan di produksi tanpa diminta.');
        $this->assertSame(0, User::query()->where('email', 'sekretaris@raab.test')->count());
    }

    public function test_seeder_berjalan_di_produksi_bila_sakelarnya_dinyalakan(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $asli = $this->setelEnv([
            'APP_JALANKAN_SEED_DEMO' => 'true',
            'SEED_DEMO_PASSWORD' => 'SandiUjiProduksi2026',
        ]);

        try {
            /*
             * Jebakan yang membuat sakelar ini tidak pernah terbaca: env()
             * TIDAK mengembalikan nilai apa adanya — 'true' dan 'false'
             * diubahnya menjadi boolean sungguhan. Perbandingan terhadap string
             * 'true' karena itu selalu salah.
             *
             * Diperiksa di sini supaya perilakunya tetap terjaga: kalau suatu
             * saat Laravel berhenti mengubahnya, seeder tetap benar karena
             * memakai filter_var(), tetapi uji ini perlu ditinjau ulang.
             */
            $this->assertTrue(env('APP_JALANKAN_SEED_DEMO'), 'env() mengubah "true" menjadi boolean.');
            $this->assertSame('SandiUjiProduksi2026', env('SEED_DEMO_PASSWORD'), 'env() tidak melihat kata sandi.');

            $this->artisan('db:seed', ['--class' => DemoPeranSeeder::class, '--force' => true])
                ->assertSuccessful();
        } finally {
            $this->pulihkanEnv($asli);
        }

        $user = User::query()->where('email', 'sekretaris@raab.test')->firstOrFail();

        /*
         * Kata sandinya HARUS dari SEED_DEMO_PASSWORD, bukan nilai bawaan di
         * kode. Nilai bawaan itu tertulis di repositori — memakainya di server
         * sungguhan sama dengan mengumumkan kata sandi sekretaris dan
         * bendahara kepada siapa pun yang bisa membaca repositori.
         */
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('SandiUjiProduksi2026', $user->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check(DemoPeranSeeder::KATA_SANDI, $user->password));
    }

    public function test_seeder_menolak_produksi_bila_kata_sandinya_kosong(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $asli = $this->setelEnv([
            'APP_JALANKAN_SEED_DEMO' => 'true',
            'SEED_DEMO_PASSWORD' => '',
        ]);

        try {
            $this->artisan('db:seed', ['--class' => DemoPeranSeeder::class, '--force' => true])
                ->assertSuccessful();
        } finally {
            $this->pulihkanEnv($asli);
        }

        // Lebih baik tidak ada akun demo daripada akun demo yang kata sandinya
        // tertulis di repositori.
        $this->assertSame(0, User::query()->where('email', 'sekretaris@raab.test')->count());
    }

    public function test_seeder_tidak_menimpa_kata_sandi_akun_yang_sudah_ada(): void
    {
        $pengurus = User::factory()->create([
            'email' => 'sekretaris@raab.test',
            'password' => \Illuminate\Support\Facades\Hash::make('SandiAsliPengurus2026'),
            'email_verified_at' => now(),
        ]);

        $this->jalankanSeedDemo();

        $pengurus->refresh();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('SandiAsliPengurus2026', $pengurus->password),
            'Kata sandi pengurus ditimpa oleh seeder demo.'
        );
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check(DemoPeranSeeder::KATA_SANDI, $pengurus->password));
        $this->assertTrue($pengurus->hasRole('sekretaris'), 'Perannya tetap harus terpasang.');
    }

    public function test_seeder_membuat_anggota_demo_sendiri_saat_basis_data_kosong(): void
    {
        /*
         * Sengaja TIDAK memanggil buatAnggota(): inilah keadaan basis data
         * produksi yang baru — belum ada satu pun anggota.
         *
         * Versi sebelumnya meminjam anggota yang sudah ada, sehingga akun
         * kader@ dan alumni@ tidak pernah terbuat di sana. Lebih buruk lagi,
         * bila ada anggota sungguhan, akun ORANG ITU yang diambil alih.
         */
        $this->jalankanSeedDemo();

        foreach (['kader@raab.test' => Member::STATUS_AKTIF, 'alumni@raab.test' => Member::STATUS_ALUMNI] as $email => $status) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertNotNull($user->member, "Akun {$email} tidak punya data anggota.");
            $this->assertSame($status, $user->member->status);
        }

        $alumni = User::query()->where('email', 'alumni@raab.test')->firstOrFail()->member;

        $this->assertNotNull($alumni->profilAlumni, 'Alumni demo perlu profil agar direktori alumni tidak kosong.');
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
