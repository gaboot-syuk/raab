<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route as Rute;
use Illuminate\Support\Facades\Route;

/**
 * Audit izin seluruh rute.
 *
 * KENAPA PERINTAH INI ADA. Pembatasan akses di aplikasi ini ditulis satu per
 * satu di `routes/web.php`, dan satu baris yang lupa ditulis tidak menimbulkan
 * galat apa pun — halamannya cuma terbuka untuk orang yang seharusnya tidak
 * boleh membukanya. Tidak ada yang gagal, tidak ada yang berteriak. Karena itu
 * pemeriksaannya harus otomatis, bukan mengandalkan ketelitian saat menulis.
 *
 * TIGA HAL YANG DICARI:
 *
 *   1. Rute GET yang terbuka untuk umum tanpa autentikasi DAN tidak diawali
 *      awalan yang memang untuk publik. Ini yang paling berbahaya: satu rute
 *      panel yang lupa dijaga langsung terbaca siapa saja.
 *   2. Rute panel yang butuh autentikasi tetapi TIDAK menyebut izin apa pun.
 *      Sebagian memang benar begitu (dasbor pribadi, notifikasi, profil
 *      sendiri), jadi ada daftar pengecualian yang ditulis terang-terangan.
 *   3. Rute yang menyebut izin TANPA autentikasi. Ini pasti salah tulis, dan
 *      akibatnya izinnya tidak pernah diperiksa.
 */
class AuditIzin extends Command
{
    protected $signature = 'audit:izin {--sertakan-publik : Tampilkan juga rute publik yang memang disengaja}';

    protected $description = 'Memeriksa rute yang terbuka tanpa autentikasi atau tanpa izin';

    /**
     * Awalan nama rute yang memang untuk publik.
     *
     * Ditulis sebagai awalan, bukan daftar nama lengkap, supaya rute publik
     * baru tidak otomatis dianggap temuan. Yang MENYIMPANG dari awalan inilah
     * yang perlu dilihat manusia.
     *
     * @var array<int, string>
     */
    private const AWALAN_PUBLIK = [
        'public.',
        'arsip.unduh',
        'pendaftaran.',
        // Pemicu penjadwal: dijangkau tanpa masuk, dan memang harus begitu —
        // yang memanggilnya layanan penjadwal, bukan manusia. Perlindungannya
        // token di dalam alamat, bukan sesi pengguna.
        'internal.',
    ];

    /**
     * Rute bawaan kerangka kerja dan paket yang memang harus terbuka.
     *
     * Ditulis sebagai URI, bukan nama, karena sebagian di antaranya tidak
     * bernama sama sekali (mis. rute penyajian berkas milik Laravel).
     * Pengecualian di sini harus disertai alasan — kalau tidak, daftar ini
     * perlahan berubah menjadi tempat menyembunyikan temuan.
     *
     * @var array<string, string>
     */
    private const URI_DISENGAJA = [
        'login' => 'Form masuk — harus bisa dibuka sebelum punya sesi.',
        'forgot-password' => 'Permintaan tautan atur ulang sandi.',
        'reset-password' => 'Halaman atur ulang sandi — tautannya memuat token sekali pakai.',
        'register' => 'Pendaftaran akun kader.',
        'two-factor-challenge' => 'Tantangan 2FA — dibuka tepat sebelum sesi terbentuk.',
        'passkeys/login' => 'Masuk dengan passkey — bagian dari proses masuk.',
        'up' => 'Pemeriksaan kesehatan untuk pemantau layanan.',
        'storage/' => 'Penyajian berkas disk privat; hanya melayani URL BERTANDA TANGAN yang dibuat server (lihat ServeFile Laravel).',
        '_inertia/devtools' => 'Perkakas pengembangan Inertia; dijaga middleware Authorize milik paketnya sendiri dan tidak aktif di produksi.',
        '_ignition' => 'Halaman galat Ignition — hanya aktif saat APP_DEBUG menyala.',
    ];

    /**
     * Rute terautentikasi yang memang TIDAK punya izin, karena isinya milik
     * orang yang sedang masuk dan bukan milik peran tertentu.
     *
     * @var array<int, string>
     */
    private const TANPA_IZIN_DISENGAJA = [
        // 'panel' sengaja TIDAK ada di sini. Daftar ini dicocokkan sebagai
        // AWALAN, jadi memasukkan 'panel' akan mengecualikan seluruh rute
        // panel.* — dan pemeriksaan "panel tanpa izin" berhenti memeriksa apa
        // pun. Rute tunggal yang memang sengaja terbuka ada di
        // TANPA_IZIN_TEPAT.
        'anggota.',
        'logout',
        'notifikasi',
        'notifikasi.baca',
        'notifikasi.baca-semua',
        'notifikasi.preferensi',
        'passkey.',
        'password.',
        'two-factor.',
        'user-password.update',
        'user-profile.',
        'verification.',
    ];

    /**
     * Rute tunggal tanpa izin yang sengaja terbuka.
     *
     * Dipisahkan dari daftar di atas karena dicocokkan PERSIS, bukan sebagai
     * awalan: 'panel' harus mengecualikan halaman /panel saja, bukan setiap
     * rute yang namanya berawalan panel.
     *
     * @var array<int, string>
     */
    private const TANPA_IZIN_TEPAT = [
        'panel', // dasbor panel di /panel; jalan masuk panel itu sendiri
    ];

    public function handle(): int
    {
        $butuhDilihat = [];
        $terlaluKetat = [];
        $publik = 0;

        foreach (Route::getRoutes() as $rute) {
            $nama = $rute->getName() ?? '(tanpa nama)';
            $middleware = $rute->gatherMiddleware();

            $adaAuth = $this->punya($middleware, 'auth');
            $izin = $this->izin($middleware);
            $metode = implode('|', array_diff($rute->methods(), ['HEAD', 'OPTIONS']));

            // 3. Izin tanpa autentikasi — selalu salah tulis.
            if ($izin !== [] && ! $adaAuth) {
                $terlaluKetat[] = [$metode, $rute->uri(), $nama, implode(' + ', $izin), 'penyebutan izin tanpa autentikasi'];

                continue;
            }

            if ($adaAuth) {
                // 2. Rute panel tanpa izin, di luar pengecualian yang disengaja.
                if ($this->panel($rute->uri()) && $izin === [] && ! $this->dikecualikan($nama)) {
                    $butuhDilihat[] = [$metode, $rute->uri(), $nama, '—', 'halaman panel tanpa izin'];
                }

                continue;
            }

            // 1. GET tanpa autentikasi — apakah memang disengaja publik?
            if ($metode === '' || $metode === 'GET') {
                if ($this->publikDisengaja($nama) || $this->uriDisengaja($rute->uri())) {
                    $publik++;
                } else {
                    $butuhDilihat[] = [$metode ?: 'GET', $rute->uri(), $nama, '—', 'terbuka untuk umum tanpa autentikasi'];
                }
            }
        }

        $this->newLine();
        $this->line('  Rute publik yang disengaja : '.$publik);

        if ($this->option('sertakan-publik') && $publik > 0) {
            $this->line('  (rute publik tidak dirinci — pakai `php artisan route:list` bila perlu)');
        }

        if ($butuhDilihat !== []) {
            $this->newLine();
            $this->error('  PERLU DILIHAT ('.count($butuhDilihat).') — rute ini terjangkau tanpa izin yang jelas:');
            $this->table(['Metode', 'URI', 'Nama', 'Izin', 'Sebab'], $butuhDilihat);
        }

        if ($terlaluKetat !== []) {
            $this->newLine();
            $this->error('  SALAH TULIS ('.count($terlaluKetat).') — izin disebut tanpa autentikasi, sehingga tidak pernah diperiksa:');
            $this->table(['Metode', 'URI', 'Nama', 'Izin', 'Sebab'], $terlaluKetat);
        }

        if ($butuhDilihat === [] && $terlaluKetat === []) {
            $this->newLine();
            $this->info('  Tidak ada temuan. Setiap rute terjangkau hanya dengan autentikasi, izin, atau memang sengaja dibuka untuk publik.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('  Periksa daftar di atas satu per satu. Kalau sebuah rute memang sengaja dibuka,');
        $this->warn('  tambahkan namanya pada daftar pengecualian di App\\Console\\Commands\\AuditIzin.');

        return self::FAILURE;
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function punya(array $middleware, string $awalan): bool
    {
        foreach ($middleware as $m) {
            if ($m === $awalan || str_starts_with($m, $awalan.':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function izin(array $middleware): array
    {
        $hasil = [];

        foreach ($middleware as $m) {
            if (str_starts_with($m, 'permission:') || str_starts_with($m, 'role:')) {
                $hasil[] = $m;
            }
        }

        return $hasil;
    }

    private function panel(string $uri): bool
    {
        return str_starts_with($uri, 'panel');
    }

    private function dikecualikan(string $nama): bool
    {
        if (in_array($nama, self::TANPA_IZIN_TEPAT, true)) {
            return true;
        }

        foreach (self::TANPA_IZIN_DISENGAJA as $awalan) {
            if ($nama === $awalan || str_starts_with($nama, rtrim($awalan, '.').'.')) {
                return true;
            }
        }

        return false;
    }

    private function publikDisengaja(string $nama): bool
    {
        foreach (self::AWALAN_PUBLIK as $awalan) {
            if (str_starts_with($nama, $awalan)) {
                return true;
            }
        }

        return false;
    }

    private function uriDisengaja(string $uri): bool
    {
        foreach (array_keys(self::URI_DISENGAJA) as $awalan) {
            if ($uri === rtrim($awalan, '/') || str_starts_with($uri, $awalan)) {
                return true;
            }
        }

        return false;
    }
}
