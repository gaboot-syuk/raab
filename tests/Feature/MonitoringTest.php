<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PemeriksaBasisData;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Fase 9D — pemantauan.
 *
 * Dua hal yang diuji di sini, dan keduanya bukan soal "halaman ada":
 *
 * 1. Halaman galat TIDAK boleh bergantung pada apa pun. Ia muncul justru saat
 *    basis data mati atau bundel aset belum terbangun. Kalau ia memakai layout
 *    yang memanggil Pengaturan::semua() atau @vite, ia akan gagal dengan cara
 *    yang sama seperti halaman yang sedang dilaporkannya — dan pengunjung
 *    hanya melihat halaman putih.
 *
 * 2. Halaman diagnostik membantu MENEMUKAN masalah, jadi ia tidak boleh
 *    menjadi kebocoran baru. Kata sandi SMTP dan kunci aplikasi tidak boleh
 *    ikut terkirim ke peramban, sekalipun hanya kepada Superadmin.
 */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            PageSeeder::class,
        ]);

        /*
         * Penyimpanan dipalsukan.
         *
         * Tanpa ini, uji "belum ada cadangan" akan lulus atau gagal
         * bergantung pada berkas di disk mesin yang menjalankannya — dan uji
         * yang hasilnya bergantung pada keadaan mesin bukan uji, melainkan
         * kebetulan.
         */
        Storage::fake('local');
    }

    private function superadmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('superadmin');

        return $user;
    }

    /**
     * Tidak semua pengguna berperan. Kadernya punya akun, tetapi tidak punya
     * peran pengurus — dan justru itulah yang perlu diuji: halaman panel harus
     * menolaknya, bukan melayaninya karena ia "sudah masuk".
     */
    private function kader(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /* ------------------------------------------------------------------ */
    /* Halaman galat                                                       */
    /* ------------------------------------------------------------------ */

    public function test_halaman_404_menampilkan_pesan_yang_ramah(): void
    {
        $respons = $this->get('/jalur-yang-tidak-pernah-ada');

        $respons->assertStatus(404);
        $respons->assertSee('Halaman tidak ditemukan');
        $respons->assertSee('Alamat yang kamu buka tidak ada di situs ini', false);
        // Bukan halaman bawaan Laravel.
        $respons->assertDontSee('Laravel', false);
        // Petunjuk jalan keluar, bukan hanya pemberitahuan.
        $respons->assertSee('Kembali ke beranda');
    }

    public function test_semua_halaman_galat_bisa_dirender(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $kode) {
            $html = view("errors.{$kode}")->render();

            $this->assertStringContainsString((string) $kode, $html, "Halaman {$kode} tidak memuat kodenya.");
            $this->assertStringContainsString('Kembali ke beranda', $html, "Halaman {$kode} tidak memberi jalan keluar.");
        }
    }

    /**
     * Halaman galat harus berdiri sendiri.
     *
     * Yang diperiksa di sini bukan selera penulisan, melainkan ketergantungan:
     * kalau halaman galat memakai @vite atau memanggil pengaturan situs dari
     * basis data, ia akan ikut mati bersama hal yang sedang rusak.
     */
    public function test_halaman_galat_tidak_bergantung_pada_aset_atau_basis_data(): void
    {
        foreach ([404, 500, 503] as $kode) {
            $html = view("errors.{$kode}")->render();

            $this->assertStringNotContainsString('@vite', $html);
            $this->assertStringNotContainsString('/build/assets', $html, "Halaman {$kode} memuat bundel aset.");
            // Gayanya ditulis langsung di dalam berkas.
            $this->assertStringContainsString('<style>', $html);
        }
    }

    public function test_halaman_galat_tidak_diindeks_mesin_pencari(): void
    {
        foreach ([404, 500] as $kode) {
            $this->assertStringContainsString(
                'noindex',
                view("errors.{$kode}")->render(),
                "Halaman {$kode} bisa terindeks mesin pencari."
            );
        }
    }

    public function test_akses_panel_tanpa_izin_menampilkan_halaman_403_yang_jelas(): void
    {
        // Kader tidak punya izin mengelola inventaris.
        $respons = $this->actingAs($this->kader())->get('/panel/inventaris');

        $respons->assertStatus(403);
        $respons->assertSee('Akses ditolak');
        $respons->assertSee('Kamu tidak punya hak untuk membuka halaman ini', false);
    }

    /* ------------------------------------------------------------------ */
    /* Diagnostik                                                          */
    /* ------------------------------------------------------------------ */

    public function test_tamu_tidak_bisa_membuka_diagnostik(): void
    {
        $this->get('/panel/diagnostik')->assertRedirect('/login');
    }

    public function test_kader_bukan_superadmin_tidak_bisa_membuka_diagnostik(): void
    {
        $this->actingAs($this->kader())->get('/panel/diagnostik')->assertStatus(403);
    }

    public function test_superadmin_bisa_membuka_diagnostik(): void
    {
        $respons = $this->actingAs($this->superadmin())->get('/panel/diagnostik');

        $respons->assertOk();
        $respons->assertSee('Diagnostik');

        // Yang diperiksa adalah DATA yang dikirim ke halaman, bukan teks yang
        // tampak. Halaman panel dirender Vue di peramban, jadi isinya tidak
        // ada di HTML mentah — memeriksa HTML di sini hanya akan menguji
        // cangkangnya, bukan isinya.

        $halaman = $respons->viewData('page');
        $this->assertIsArray($halaman['props']);
        $this->assertArrayHasKey('aplikasi', $halaman['props']);
        $this->assertArrayHasKey('basisData', $halaman['props']);
        $this->assertArrayHasKey('antrean', $halaman['props']);
        $this->assertArrayHasKey('email', $halaman['props']);
        $this->assertArrayHasKey('penyimpanan', $halaman['props']);
        $this->assertArrayHasKey('penjadwal', $halaman['props']);
        $this->assertArrayHasKey('pemeriksaan', $halaman['props']);
    }

    public function test_diagnostik_melaporkan_basis_data_tersambung(): void
    {
        $respons = $this->actingAs($this->superadmin())->get('/panel/diagnostik');

        $basisData = $respons->viewData('page')['props']['basisData'];

        $this->assertTrue($basisData['tersambung']);
        $this->assertGreaterThan(10, $basisData['tabel']);
    }

    public function test_diagnostik_tidak_membocorkan_rahasia(): void
    {
        // Nilai yang sengaja dipasang: kalau bocor, akan muncul di respons.
        // Kunci aplikasi TIDAK diganti di sini: mengganti app.key membuat
        // enkripsi sesi gagal, sehingga yang teruji justru galat itu sendiri,
        // bukan kebocorannya. Yang diperiksa adalah kunci yang sedang dipakai.
        config([
            'mail.mailers.smtp.password' => 'kata-sandi-smtp-rahasia-sekali',
            'database.connections.mysql.password' => 'sandi-basis-data-rahasia',
        ]);

        $kunciAplikasi = (string) config('app.key');

        $respons = $this->actingAs($this->superadmin())->get('/panel/diagnostik');

        $respons->assertOk();
        $respons->assertDontSee('kata-sandi-smtp-rahasia-sekali', false);
        $respons->assertDontSee('sandi-basis-data-rahasia', false);
        $this->assertNotEmpty($kunciAplikasi);
        $respons->assertDontSee($kunciAplikasi, false);
    }

    public function test_diagnostik_menyebut_penjadwal_yang_terdaftar(): void
    {
        $penjadwal = $this->actingAs($this->superadmin())
            ->get('/panel/diagnostik')
            ->viewData('page')['props']['penjadwal'];

        $perintah = array_column($penjadwal, 'perintah');

        $this->assertContains('cadangan:buat', $perintah);
        $this->assertContains('pinjaman:pengingat', $perintah);

        foreach ($penjadwal as $baris) {
            $this->assertSame('Ya', $baris['terdaftar'], "Penjadwal {$baris['perintah']} tidak terdaftar.");
        }
    }

    /**
     * Halaman Diagnostik harus tetap melihat penjadwal meski daftarnya kosong.
     *
     * Keadaan itu terjadi SETIAP KALI halaman ini dibuka di peramban:
     * `routes/console.php` hanya dimuat saat aplikasi berjalan di konsol, jadi
     * pada permintaan web penjadwalnya kosong. Tanpa memuatnya lebih dulu,
     * halaman ini melaporkan "Tidak" untuk setiap perintah — dan pengurus
     * mencari masalah yang tidak ada.
     *
     * Di pengujian keadaan itu tidak pernah terjadi sendiri, karena pengujian
     * berjalan di konsol. Karena itu daftarnya dikosongkan lebih dulu.
     */
    public function test_diagnostik_tetap_melihat_penjadwal_meski_daftarnya_kosong(): void
    {
        $jadwal = app(Schedule::class);

        // Kosongkan penjadwal, seperti keadaan pada permintaan web.
        (function (): void {
            $this->events = [];
        })->call($jadwal);

        $penjadwal = $this->actingAs($this->superadmin())
            ->get('/panel/diagnostik')
            ->viewData('page')['props']['penjadwal'];

        foreach ($penjadwal as $baris) {
            $this->assertSame(
                'Ya',
                $baris['terdaftar'],
                "Penjadwal {$baris['perintah']} dilaporkan tidak terdaftar padahal seharusnya ada."
            );
        }
    }

    public function test_pemeriksaan_menandai_debug_yang_menyala(): void
    {
        config(['app.debug' => true]);

        $pemeriksaan = collect($this->actingAs($this->superadmin())
            ->get('/panel/diagnostik')
            ->viewData('page')['props']['pemeriksaan']);

        $debug = $pemeriksaan->firstWhere('apa', 'APP_DEBUG menyala');

        $this->assertNotNull($debug, 'Mode debug yang menyala tidak ditandai.');
        $this->assertSame('perlu tindakan', $debug['keadaan']);
    }

    public function test_pemeriksaan_menandai_belum_ada_cadangan(): void
    {
        $pemeriksaan = collect($this->actingAs($this->superadmin())
            ->get('/panel/diagnostik')
            ->viewData('page')['props']['pemeriksaan']);

        $cadangan = $pemeriksaan->firstWhere('apa', 'Belum ada cadangan');

        $this->assertNotNull($cadangan, 'Ketidakadaan cadangan tidak ditandai.');
        $this->assertSame('perlu tindakan', $cadangan['keadaan']);
    }

    /* ------------------------------------------------------------------ */
    /* Pemicu penjadwal (hosting tanpa cron)                               */
    /* ------------------------------------------------------------------ */

    /**
     * Fitur yang belum dikonfigurasi harus MATI, bukan terbuka.
     *
     * Selama SCHEDULER_TOKEN kosong, jalur ini tidak boleh menjalankan apa pun.
     * Membiarkannya berjalan tanpa token berarti siapa pun bisa memicu
     * pembuatan cadangan berulang kali sampai ruang penyimpanan penuh.
     */
    public function test_pemicu_penjadwal_mati_bila_token_belum_diatur(): void
    {
        config(['services.scheduler.token' => null]);

        $this->get('/internal/scheduler/apa-saja')->assertNotFound();
    }

    public function test_pemicu_penjadwal_menolak_token_yang_salah(): void
    {
        config(['services.scheduler.token' => 'token-yang-benar-panjang-sekali']);

        $this->get('/internal/scheduler/token-yang-salah')->assertForbidden();
    }

    public function test_pemicu_penjadwal_menjalankan_perintah_yang_sudah_waktunya(): void
    {
        config(['services.scheduler.token' => 'token-uji-penjadwal']);

        Cache::forget('penjadwal-berjalan');

        // Peristiwa uji yang pasti sudah waktunya, supaya yang diuji benar-benar
        // "apakah perintah dijalankan", bukan sekadar bentuk jawabannya.
        app(Schedule::class)
            ->call(fn () => Cache::put('penjadwal-berjalan', true))
            ->everyMinute()
            ->description('uji-penjadwal');

        $respons = $this->getJson('/internal/scheduler/token-uji-penjadwal');

        $respons->assertOk();
        $respons->assertJsonPath('galat', []);
        $this->assertContains('uji-penjadwal', $respons->json('dijalankan'));
        $this->assertTrue(Cache::get('penjadwal-berjalan'), 'Perintahnya tidak benar-benar dijalankan.');
    }

    public function test_alamat_penjadwal_tidak_berprefiks_bahasa(): void
    {
        config(['services.scheduler.token' => 'token-uji-penjadwal']);

        // Alamatnya harus satu. Kalau ia ikut berprefiks bahasa, memanggilnya
        // dari layanan penjadwal jadi tidak bisa diandalkan.
        $this->get('/en/internal/scheduler/token-uji-penjadwal')->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Pemeriksaan kesehatan                                               */
    /* ------------------------------------------------------------------ */

    public function test_titik_kesehatan_menjawab_ok(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('basis_data', 'terjangkau');
    }

    public function test_alamat_kesehatan_tidak_berprefiks_bahasa(): void
    {
        /*
         * Sama seperti pemicu penjadwal: layanan pemantau tidak tahu apa-apa
         * soal bahasa situs ini, dan alamat yang bercabang membuat
         * pendaftarannya mudah salah.
         */
        $this->get('/en/health')->assertNotFound();
    }

    public function test_titik_kesehatan_menjawab_503_saat_basis_data_gagal(): void
    {
        /*
         * Pemeriksanya disuntik, bukan dengan mengganti `database.default`.
         * Cara itu memang membuat sambungannya gagal, tetapi juga meninggalkan
         * transaksi uji dalam keadaan terbuka dan menumbangkan enam uji
         * berikutnya.
         */
        $this->app->instance(PemeriksaBasisData::class, new class extends PemeriksaBasisData
        {
            public function terjangkau(): bool
            {
                return false;
            }
        });

        $this->get('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'terganggu')
            ->assertJsonPath('basis_data', 'tidak terjangkau');
    }

    public function test_titik_kesehatan_tidak_membocorkan_rincian_sambungan(): void
    {
        $this->app->instance(PemeriksaBasisData::class, new class extends PemeriksaBasisData
        {
            public function terjangkau(): bool
            {
                return false;
            }
        });

        $isi = (string) $this->get('/health')->getContent();

        // Jalur ini terbuka tanpa masuk, jadi jawabannya tidak boleh memuat
        // apa pun yang berguna bagi yang sedang memetakan sasaran.
        foreach (['password', 'defaultdb', 'avnadmin', 'aivencloud', 'SQLSTATE'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $isi, "Titik kesehatan membocorkan: {$rahasia}");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Audit izin                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Surat yang gagal terkirim harus meninggalkan jejak yang bisa dibaca.
     *
     * Tanpa pencatatan ini, kegagalan pengiriman hanya berakhir di tabel
     * `failed_jobs` — tempat yang tidak dibuka siapa pun kecuali ada yang sudah
     * tahu ada masalah. Padahal hampir semua pekerjaan antrean di aplikasi ini
     * adalah pengiriman surat.
     */
    public function test_kegagalan_pekerjaan_antrean_dicatat_ke_log(): void
    {
        Log::spy();

        $pekerjaan = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $pekerjaan->shouldReceive('resolveName')->andReturn('App\\Notifications\\PengingatPeminjaman');
        $pekerjaan->shouldReceive('getQueue')->andReturn('default');

        (new \App\Listeners\CatatPekerjaanGagal)->handle(new JobFailed(
            'database',
            $pekerjaan,
            new \RuntimeException('SMTP tidak bisa dihubungi'),
        ));

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $pesan, array $konteks): bool {
                return $pesan === 'Pekerjaan antrean gagal.'
                    && $konteks['pekerjaan'] === 'App\\Notifications\\PengingatPeminjaman'
                    && str_contains($konteks['galat'], 'SMTP');
            });
    }

    public function test_pendengar_kegagalan_antrean_terdaftar(): void
    {
        $pendengar = Event::getListeners(JobFailed::class);

        $this->assertNotEmpty($pendengar, 'Tidak ada pendengar untuk JobFailed.');
    }

    /**
     * Catatan harus tetap dibuat meski muatan pekerjaannya rusak.
     *
     * Pekerjaan bisa gagal justru KARENA muatannya tidak terbaca. Kalau upaya
     * mencatatnya ikut meledak, jejaknya hilang sama sekali — persis pada
     * kejadian yang paling perlu diketahui.
     */
    public function test_pencatatan_tetap_jalan_meski_nama_pekerjaan_tidak_terbaca(): void
    {
        Log::spy();

        $pekerjaan = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $pekerjaan->shouldReceive('resolveName')->andThrow(new \RuntimeException('muatan rusak'));
        $pekerjaan->shouldReceive('getQueue')->andThrow(new \RuntimeException('muatan rusak'));

        (new \App\Listeners\CatatPekerjaanGagal)->handle(new JobFailed(
            'database',
            $pekerjaan,
            new \RuntimeException('gagal'),
        ));

        Log::shouldHaveReceived('error')->once();
    }

    /**
     * Pemeriksaan "rute panel tanpa izin" pernah tidak memeriksa apa pun.
     *
     * Penyebabnya adalah entri 'panel' di daftar pengecualian: daftar itu
     * dicocokkan sebagai AWALAN, sehingga setiap rute bernama panel.* otomatis
     * lolos. Uji ini menjaga agar entri itu tidak dimasukkan kembali.
     */
    public function test_audit_izin_tidak_mengecualikan_seluruh_rute_panel(): void
    {
        $this->artisan('audit:izin')->assertExitCode(0);

        $rute = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter(fn ($n) => $n !== null && ($n === 'panel' || str_starts_with($n, 'panel.')))
            ->all();

        $this->assertNotEmpty($rute);

        $terkecuali = [];
        foreach ($rute as $nama) {
            if ($this->panggilDikecualikan($nama)) {
                $terkecuali[] = $nama;
            }
        }

        // Hanya rute panel yang memang sengaja terbuka.
        $this->assertContains('panel', $terkecuali);
        $this->assertNotContains('panel.cadangan', $terkecuali);
        $this->assertNotContains('panel.diagnostik', $terkecuali);
    }

    private function panggilDikecualikan(string $nama): bool
    {
        $perintah = app(\App\Console\Commands\AuditIzin::class);
        $metode = new \ReflectionMethod($perintah, 'dikecualikan');
        $metode->setAccessible(true);

        return (bool) $metode->invoke($perintah, $nama);
    }
}
