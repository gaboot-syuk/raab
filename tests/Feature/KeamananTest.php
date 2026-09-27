<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\Captcha;
use App\Support\KataSandi;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocialLinkSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Keamanan: header, izin, dan tidak bocornya data pribadi ke publik.
 *
 * UJI UTAMA DI BERKAS INI ADALAH PENYISIRAN, bukan pemeriksaan satu per satu.
 * Memeriksa "halaman panel X menolak tamu" itu mudah dan selalu lulus, karena
 * yang diperiksa adalah halaman yang memang sudah dipikirkan. Yang berbahaya
 * adalah halaman yang BARU DITAMBAHKAN dan lupaan dijaga — dan itu hanya
 * tertangkap kalau SELURUH rute disisir.
 */
class KeamananTest extends TestCase
{
    use RefreshDatabase;

    /** Nilai yang sengaja dipasang pada data contoh; harus tidak pernah muncul di halaman publik. */
    private const RAHASIA = [
        'NIM' => '221234567890',
        'TELEPON' => '081999888777',
        'EMAIL' => 'kader.rahasia@contoh.test',
    ];

    /* ============= Seeder data awal (dipakai saat deploy) ============= */

    /**
     * Seeder superadmin TIDAK boleh menimpa kata sandi yang sudah ada.
     *
     * Seeder ini ikut berjalan pada penyebaran berikutnya. Kalau ia menimpa,
     * sandi yang sudah diganti pengurus akan kembali ke nilai di .env tanpa
     * pemberitahuan — dan nilai itu tertulis di tempat yang mungkin terbaca
     * orang lain. Bukan sekadar mengganggu: itu penurunan keamanan yang
     * terjadi diam-diam pada setiap deploy.
     */
    public function test_seeder_superadmin_tidak_menimpa_kata_sandi_yang_sudah_ada(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create([
            'email' => 'ketua@raab.test',
            'password' => Hash::make('SandiPilihanSendiri2026'),
        ]);

        $this->seed(SuperadminSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('SandiPilihanSendiri2026', $user->password));
        $this->assertFalse(Hash::check('rahasia123', $user->password));
    }

    public function test_seeder_superadmin_membuat_akun_saat_belum_ada(): void
    {
        $this->seed([RolePermissionSeeder::class, SuperadminSeeder::class]);

        $user = User::query()->where('email', 'ketua@raab.test')->firstOrFail();

        $this->assertTrue($user->hasRole('superadmin'));
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Variabel lingkungan yang DIBIARKAN KOSONG bukan berarti "pakai nilai
     * bawaan".
     *
     * Hosting menyimpannya sebagai string kosong, dan env() mengembalikan
     * string kosong itu alih-alih nilai bawaannya. Kalau tidak dijaga, akun
     * superadmin terbuat dengan email atau kata sandi kosong: akunnya ada,
     * situsnya menyala, tetapi tidak ada yang bisa masuk — dan tanpa akses
     * shell tidak ada cara memperbaikinya dari dalam.
     */
    public function test_seeder_superadmin_menolak_kredensial_kosong(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $asli = [
            'SEED_SUPERADMIN_EMAIL' => getenv('SEED_SUPERADMIN_EMAIL'),
            'SEED_SUPERADMIN_PASSWORD' => getenv('SEED_SUPERADMIN_PASSWORD'),
        ];

        putenv('SEED_SUPERADMIN_EMAIL=');
        putenv('SEED_SUPERADMIN_PASSWORD=');

        try {
            $this->seed(SuperadminSeeder::class);
        } finally {
            foreach ($asli as $kunci => $nilai) {
                $nilai === false ? putenv($kunci) : putenv("{$kunci}={$nilai}");
            }
        }

        $this->assertDatabaseCount('users', 0);
    }

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

    private function anggotaRahasia(): Member
    {
        $user = User::factory()->create(['name' => 'Kader Sangat Rahasia', 'email' => self::RAHASIA['EMAIL']]);

        $anggota = new Member;
        $anggota->user_id = $user->id;
        $anggota->nomor_anggota = 'RAAB-2026-9001';
        $anggota->nama_lengkap = 'Kader Sangat Rahasia';
        $anggota->status = Member::STATUS_AKTIF;
        $anggota->jalur = Member::JALUR_KADER;
        $anggota->nim = self::RAHASIA['NIM'];
        $anggota->telepon = self::RAHASIA['TELEPON'];
        $anggota->save();

        return $anggota;
    }

    /* ===================== Header keamanan ===================== */

    public function test_halaman_publik_membawa_header_keamanan(): void
    {
        $respons = $this->get('/');

        $respons->assertOk();
        $respons->assertHeader('X-Content-Type-Options', 'nosniff');
        $respons->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respons->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respons->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $respons->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $csp = $respons->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Content-Security-Policy tidak dikirim.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_halaman_galat_juga_membawa_header_keamanan(): void
    {
        // Header yang hanya menempel pada halaman "normal" tidak menolong
        // justru pada saat yang paling perlu ditolong.
        $respons = $this->get('/alamat-yang-tidak-ada');

        $respons->assertNotFound();
        $respons->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotNull($respons->headers->get('Content-Security-Policy'));
    }

    public function test_hsts_hanya_dikirim_lewat_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_kebijakan_keamanan_mengizinkan_sumber_yang_memang_dipakai(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        // Peta alumni & QR kartu kader memakai pustaka dari unpkg.
        $this->assertStringContainsString('https://unpkg.com', $csp);
        // Ubin peta OpenStreetMap.
        $this->assertStringContainsString('tile.openstreetmap.org', $csp);
        // Pratinjau unggahan sebelum berkas dikirim.
        $this->assertStringContainsString('blob:', $csp);
    }

    /* ===================== Audit izin ===================== */

    public function test_audit_izin_tidak_menemukan_temuan(): void
    {
        // Perintah ini memeriksa SELURUH rute: yang terbuka tanpa autentikasi,
        // yang masuk panel tanpa izin, dan yang menyebut izin tanpa autentikasi.
        $this->artisan('audit:izin')->assertExitCode(0);
    }

    /* ===================== Penyisiran rute panel & anggota ===================== */

    public function test_tidak_ada_rute_panel_atau_anggota_yang_terbuka_untuk_tamu(): void
    {
        $bocor = [];

        foreach (Route::getRoutes() as $rute) {
            $uri = $rute->uri();

            if (! in_array('GET', $rute->methods(), true)) {
                continue;
            }

            $terjaga = str_starts_with($uri, 'panel')
                || in_array($uri, ['dasbor', 'profil', 'kartu-kader', 'notifikasi'], true)
                || str_starts_with($uri, 'prestasi-saya')
                || str_starts_with($uri, 'arsip-internal')
                || str_starts_with($uri, 'pengumuman-internal')
                || str_starts_with($uri, 'kontribusi')
                || str_starts_with($uri, 'pustaka')
                || str_starts_with($uri, 'iuran')
                || str_starts_with($uri, 'hibah')
                || str_starts_with($uri, 'kegiatan')
                || str_starts_with($uri, 'karya');

            if (! $terjaga) {
                continue;
            }

            // Parameter diganti nilai apa pun: yang diperiksa adalah apakah
            // middleware-nya bekerja, bukan apakah datanya ada.
            $jalur = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);
            $status = $this->get($jalur)->getStatusCode();

            if ($status < 300 || $status === 404) {
                $bocor[] = $jalur.' → '.$status;
            }
        }

        $this->assertSame([], $bocor, "Rute berikut terjangkau tamu:\n".implode("\n", $bocor));
    }

    /* ===================== Penyisiran halaman publik ===================== */

    public function test_tidak_ada_data_pribadi_kader_yang_bocor_ke_halaman_publik(): void
    {
        $anggota = $this->anggotaRahasia();
        $anggota->profil_publik = true;
        $anggota->save();

        $bocor = [];
        $diperiksa = 0;

        foreach (Route::getRoutes() as $rute) {
            $nama = $rute->getName() ?? '';
            $uri = $rute->uri();

            if (! str_starts_with($nama, 'public.') || str_contains($uri, '{')) {
                continue;
            }

            // Rute unduhan berkas diperiksa terpisah; isinya bukan HTML.
            if (str_contains($uri, '/unduh')) {
                continue;
            }

            $respons = $this->get('/'.$uri);

            if (! $respons->isSuccessful()) {
                continue;
            }

            $diperiksa++;
            $isi = (string) $respons->getContent();

            foreach (self::RAHASIA as $jenis => $nilai) {
                if (str_contains($isi, $nilai)) {
                    $bocor[] = $uri.' membocorkan '.$jenis;
                }
            }
        }

        $this->assertGreaterThan(10, $diperiksa, 'Terlalu sedikit halaman publik yang berhasil diperiksa — penyisirannya sepertinya tidak berjalan.');
        $this->assertSame([], $bocor, "Data pribadi bocor di:\n".implode("\n", $bocor));
    }

    public function test_situs_publik_tidak_membocorkan_kunci_rahasia(): void
    {
        foreach (['/', '/kontak', '/aspirasi', '/pengumuman', '/arsip'] as $jalur) {
            $respons = $this->get($jalur);

            if (! $respons->isSuccessful()) {
                continue;
            }

            $isi = (string) $respons->getContent();

            foreach (['APP_KEY', 'DB_PASSWORD', 'CAPTCHA_RAHASIA', 'MAIL_PASSWORD'] as $penanda) {
                $this->assertStringNotContainsString($penanda, $isi, $jalur.' menyebut nama variabel rahasia.');
            }

            // Kunci aplikasi yang sesungguhnya tidak boleh ikut ter-render.
            $this->assertStringNotContainsString((string) config('app.key'), $isi);
        }
    }

    /* ===================== Berkas disk privat ===================== */

    public function test_berkas_disk_privat_tidak_bisa_diambil_lewat_alamat_langsung(): void
    {
        // Rute `storage/{path}` milik Laravel hanya melayani URL BERTANDA
        // TANGAN. Kalau sifat itu berubah, seluruh kendali akses dokumen arsip
        // dan sertifikat prestasi ikut hilang tanpa suara.
        $this->get('/storage/1/berkas-rahasia.pdf')->assertForbidden();
        $this->get('/storage/sertifikat/rahasia.pdf')->assertForbidden();
    }

    /* ===================== Captcha ===================== */

    public function test_captcha_mati_secara_bawaan_sehingga_formulir_tetap_bisa_dipakai(): void
    {
        config(['services.captcha.penyedia' => Captcha::NONE]);

        $this->assertFalse(Captcha::aktif());
        $this->assertSame(['nullable'], Captcha::aturan());
    }

    public function test_captcha_yang_dipilih_tanpa_kunci_tidak_mematikan_formulir(): void
    {
        // Keadaan setengah terpasang adalah keadaan yang paling mudah terjadi
        // saat menyiapkan hosting. Kalau dianggap aktif, seluruh formulir
        // publik langsung mati.
        config([
            'services.captcha.penyedia' => Captcha::TURNSTILE,
            'services.captcha.kunci_situs' => null,
            'services.captcha.kunci_rahasia' => null,
        ]);

        $this->assertFalse(Captcha::aktif());
    }

    public function test_captcha_aktif_mewajibkan_token(): void
    {
        config([
            'services.captcha.penyedia' => Captcha::TURNSTILE,
            'services.captcha.kunci_situs' => 'kunci-situs',
            'services.captcha.kunci_rahasia' => 'kunci-rahasia',
        ]);

        $this->assertTrue(Captcha::aktif());

        // Token kosong ditolak TANPA memanggil layanan luar.
        $this->assertFalse(Captcha::periksa(null)['berhasil']);
        $this->assertFalse(Captcha::periksa('   ')['berhasil']);
    }

    public function test_pengiriman_pesan_publik_tetap_berhasil_saat_captcha_mati(): void
    {
        config(['services.captcha.penyedia' => Captcha::NONE]);

        $this->post('/kontak', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'jenis' => 'umum',
            'subjek' => 'Tanya agenda',
            'pesan' => 'Apakah agenda kajian bulan ini sudah ada jadwalnya? Terima kasih.',
            'setuju' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('contact_messages', 1);
    }

    /* ===================== Kata sandi ===================== */

    public function test_kebijakan_kata_sandi_terpusat(): void
    {
        $this->assertSame(12, KataSandi::PANJANG_MINIMUM);

        /*
         * Dibuktikan lewat PERILAKU, bukan dengan membandingkan objek aturan:
         * yang penting bagi keamanan adalah kata sandi pendek benar-benar
         * ditolak oleh kebijakan bawaan aplikasi — termasuk pada jalur Fortify
         * (lupa sandi, atur ulang sandi, ubah sandi) yang tidak menulis
         * aturannya sendiri.
         */
        $bawaan = \Illuminate\Validation\Rules\Password::defaults();

        $lemah = Validator::make(['password' => 'pendek1'], ['password' => $bawaan]);
        $this->assertTrue($lemah->fails(), 'Kata sandi pendek seharusnya ditolak kebijakan bawaan.');

        $hanyaHuruf = Validator::make(['password' => 'hanyahurufpanjang'], ['password' => $bawaan]);
        $this->assertTrue($hanyaHuruf->fails(), 'Kata sandi tanpa angka seharusnya ditolak.');

        $kuat = Validator::make(['password' => 'kalimat panjang 2026'], ['password' => $bawaan]);
        $this->assertFalse($kuat->fails(), 'Kata sandi panjang berhuruf dan berangka seharusnya diterima.');
    }

    public function test_kata_sandi_lemah_ditolak_saat_menambah_pengguna(): void
    {
        $ketua = User::factory()->create();
        $ketua->assignRole('superadmin');

        $this->actingAs($ketua)
            ->post('/panel/pengguna', [
                'name' => 'Pengurus Baru',
                'email' => 'baru@raab.test',
                'password' => 'rahasia',
                'password_confirmation' => 'rahasia',
            ])
            ->assertSessionHasErrors('password');
    }

    /* ------------------------------------------------------------------ */
    /* Asal berkas media                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Berkas media harus SELALU diambil dari host yang sedang dibuka.
     *
     * Alamat disk `public` pernah ditulis lengkap dengan domain dari APP_URL.
     * Akibatnya, begitu APP_URL tidak sama persis dengan domain yang sedang
     * dibuka — pratinjau di port lain, staging, `www` versus tanpa `www` —
     * setiap gambar dianggap berasal dari luar dan diblokir oleh kebijakan
     * keamanan situs ini sendiri:
     *
     *   violates Content Security Policy directive: "img-src 'self' …"
     *
     * Yang terlihat pengurus: gambar hilang tanpa penjelasan. Yang tidak
     * terlihat: catatan di konsol peramban, yang tidak pernah ia buka.
     *
     * Uji ini menjaga alamatnya tetap relatif, sehingga satu kelas kesalahan
     * itu tidak bisa kembali.
     */
    public function test_alamat_berkas_media_relatif_sehingga_selalu_seasal_halaman(): void
    {
        $alamat = \Illuminate\Support\Facades\Storage::disk('public')->url('contoh/gambar.png');

        $this->assertStringStartsWith(
            '/',
            $alamat,
            "Alamat media harus relatif terhadap host, bukan memuat domain dari APP_URL. Ditemukan: {$alamat}"
        );

        $this->assertStringNotContainsString(
            'http',
            $alamat,
            "Alamat media memuat skema dan domain: {$alamat}"
        );
    }

    /**
     * Kebijakan keamanan tidak boleh memuat domain aplikasi sendiri.
     *
     * Kalau `img-src` sampai perlu menyebut domain sendiri, berarti ada berkas
     * yang diambil dari luar — dan itu gejalanya, bukan obatnya.
     */
    public function test_kebijakan_gambar_tidak_memuat_domain_sendiri(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self'", $csp);
        $this->assertStringNotContainsString(
            'localhost',
            (string) preg_replace('/.*?img-src[^;]*/', '', $csp, 1) ?: '',
        );
    }
}
