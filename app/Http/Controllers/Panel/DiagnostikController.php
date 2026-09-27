<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Services\Cadangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;

/**
 * Diagnostik — khusus Superadmin.
 *
 * Gunanya satu: menjawab "apa yang rusak?" TANPA akses SSH. Di free tier tidak
 * ada terminal, dan di shared hosting murah belum tentu ada. Tanpa halaman ini,
 * satu-satunya cara mengetahui email tidak terkirim atau penjadwal tidak jalan
 * adalah menebak.
 *
 * NILAI RAHASIA TIDAK PERNAH DITAMPILKAN. Yang ditampilkan hanya NAMA pengandar
 * dan alamat pengirim — bukan kata sandi SMTP, bukan kunci aplikasi. Halaman
 * yang membantu mendiagnosis tidak boleh menjadi tempat kebocoran yang baru.
 */
class DiagnostikController extends Controller
{
    use MenjalankanAksi;

    /**
     * Rute konsol hanya dimuat sekali per permintaan.
     *
     * Memuatnya dua kali akan MENDAFTARKAN ULANG setiap perintah terjadwal,
     * sehingga satu perintah bisa dijalankan dua kali dalam satu panggilan.
     */
    private static bool $ruteKonsolDimuat = false;

    public function __construct(private Cadangan $cadangan) {}

    public function index(): ResponsInertia
    {
        $this->muatRuteKonsol();

        return Inertia::render('Panel/Diagnostik/Index', [
            'aplikasi' => $this->aplikasi(),
            'basisData' => $this->basisData(),
            'antrean' => $this->antrean(),
            'email' => $this->email(),
            'penyimpanan' => $this->penyimpanan(),
            'penjadwal' => $this->penjadwal(),
            'log' => $this->log(),
            'pemeriksaan' => $this->pemeriksaan(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aplikasi(): array
    {
        return [
            'lingkungan' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'locale' => app()->getLocale(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'zona_waktu' => config('app.timezone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basisData(): array
    {
        $hasil = ['pengandar' => config('database.default'), 'tersambung' => false, 'tabel' => 0, 'galat' => null];

        try {
            DB::connection()->getPdo();
            $hasil['tersambung'] = true;
            $hasil['tabel'] = count(Schema::getTableListing());
        } catch (\Throwable $e) {
            $hasil['galat'] = $e->getMessage();
        }

        return $hasil;
    }

    /**
     * @return array<string, mixed>
     */
    private function antrean(): array
    {
        $gagal = 0;
        $menunggu = 0;

        try {
            $gagal = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $menunggu = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        } catch (\Throwable) {
            // Bila tabelnya belum ada, biarkan angka nol — itu memang keadaannya.
        }

        return [
            'pengandar' => config('queue.default'),
            'menunggu' => $menunggu,
            'gagal' => $gagal,
            // `sync` berarti email dikirim saat itu juga. Di produksi dengan
            // antrean `database`, tanpa pekerja antrean yang berjalan, email
            // akan menumpuk tanpa ada yang tahu.
            'catatan' => config('queue.default') === 'sync'
                ? 'Antrean berjalan langsung (sync): email dikirim saat itu juga, tanpa perlu pekerja antrean.'
                : 'Antrean memakai pengandar '.config('queue.default').'. Pastikan ada pekerja antrean yang berjalan, kalau tidak surat akan menumpuk di tabel jobs.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function email(): array
    {
        return [
            'pengirim_surat' => config('mail.default'),
            'dari' => config('mail.from.address'),
            'nama_dari' => config('mail.from.name'),
            // Kata sandi SMTP sengaja TIDAK ditampilkan. Yang perlu diketahui
            // pengurus hanyalah apakah pengirimnya sudah diisi.
            'terkonfigurasi' => (bool) config('mail.from.address'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function penyimpanan(): array
    {
        $daftar = $this->cadangan->daftar();
        $total = array_sum(array_column($daftar, 'ukuran'));

        /*
         * Disk media DIBACA DARI KONFIGURASI MEDIA, bukan dari
         * `filesystems.default`.
         *
         * Keduanya berbeda: bawaan `filesystems.default` adalah `local` (disk
         * PRIVAT), sementara unggahan gambar masuk ke `public`. Halaman ini
         * sempat melaporkan `local` sebagai "Disk media" — dan orang yang
         * sedang mencari sebab gambar tidak muncul akan memeriksa disk yang
         * salah. Keterangan yang keliru lebih berbahaya daripada tidak ada
         * keterangan, apalagi di halaman yang gunanya justru mencari sebab.
         */
        $diskMedia = (string) (config('media-library.disk_name') ?: config('filesystems.default'));

        return [
            'disk_media' => $diskMedia,
            'disk_bawaan' => config('filesystems.default'),
            'privat_bisa_ditulis' => is_writable(storage_path('app/private')),
            'publik_bisa_ditulis' => is_writable(storage_path('app/public')),
            'cadangan_jumlah' => count($daftar),
            'cadangan_total' => round($total / 1024, 1).' KB',
            'cadangan_terakhir' => $daftar[0]['dibuat'] ?? null,
        ];
    }

    /**
     * Muat `routes/console.php` bila penjadwalnya masih kosong.
     *
     * RUTE KONSOL HANYA DIMUAT SAAT APLIKASI BERJALAN DI KONSOL.
     *
     * Pada permintaan web — yaitu saat halaman ini dibuka di peramban — berkas
     * itu tidak pernah dimuat, sehingga penjadwalnya kosong dan halaman ini
     * melaporkan "Tidak" untuk SETIAP perintah. Pengurus lalu mencari masalah
     * yang tidak ada; laporan yang salah lebih buruk daripada tidak ada
     * laporan.
     *
     * Di pengujian hal ini tidak terlihat, karena pengujian memang berjalan di
     * konsol dan berkasnya sudah dimuat.
     *
     * Syaratnya "penjadwal kosong", bukan penanda "sudah pernah dimuat":
     * penanda akan membuat keadaan ini mustahil diuji, dan yang tidak bisa
     * diuji akan kembali lagi.
     */
    private function muatRuteKonsol(): void
    {
        if (static::$ruteKonsolDimuat || Schedule::events() !== []) {
            return;
        }

        static::$ruteKonsolDimuat = true;

        require base_path('routes/console.php');
    }

    /**
     * Menanyakan langsung ke penjadwal: perintah apa saja yang terdaftar.
     *
     * Yang dicari adalah NAMA perintah di dalam untai perintahnya, bukan suku
     * kata pertama. Untai itu berbentuk
     * `'/usr/local/bin/php' 'artisan' cadangan:buat`, jadi memotong suku kata
     * pertama hanya akan menghasilkan alamat PHP-nya.
     *
     * @return array<int, array<string, string>>
     */
    private function penjadwal(): array
    {
        $terdaftar = collect(Schedule::events())
            ->map(fn ($e) => (string) ($e->command ?? ''))
            ->implode("\n");

        $periksa = function (string $perintah, string $nama) use ($terdaftar): array {
            return [
                'nama' => $nama,
                'perintah' => $perintah,
                'terdaftar' => str_contains($terdaftar, $perintah) ? 'Ya' : 'Tidak',
            ];
        };

        return [
            $periksa('cadangan:buat', 'Cadangan basis data harian'),
            $periksa('pinjaman:pengingat', 'Pengingat peminjaman'),
        ];
    }

    /**
     * Cuplikan log hari ini.
     *
     * Yang diambil hanya BARIS GALAT, bukan seluruh berkas log: menampilkan
     * seluruhnya berarti menampilkan jejak tumpukan yang panjang dan
     * membingungkan, dan justru menyembunyikan baris yang penting.
     *
     * @return array<string, mixed>
     */
    private function log(): array
    {
        $berkas = storage_path('logs/laravel.log');

        if (! is_readable($berkas)) {
            return ['ada' => false, 'galat_hari_ini' => 0, 'cuplikan' => []];
        }

        $hari = now()->format('Y-m-d');
        $galat = [];
        $jumlah = 0;

        // Dibaca dari BELAKANG: yang dicari kejadian terbaru, dan berkas log
        // bisa berukuran puluhan megabita.
        $baris = $this->bacaEkor($berkas, 2000);

        foreach (array_reverse($baris) as $satu) {
            if (! str_contains($satu, '.ERROR')) {
                continue;
            }

            if (! str_contains($satu, $hari)) {
                continue;
            }

            $jumlah++;

            if (count($galat) < 10) {
                $galat[] = mb_substr(trim($satu), 0, 300);
            }
        }

        return ['ada' => true, 'galat_hari_ini' => $jumlah, 'cuplikan' => $galat];
    }

    /**
     * @return array<int, string>
     */
    private function bacaEkor(string $berkas, int $maksBaris): array
    {
        $isi = @file($berkas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($isi === false) {
            return [];
        }

        return array_slice($isi, -$maksBaris);
    }

    /**
     * Pemeriksaan yang hasilnya "perlu tindakan" atau "aman".
     *
     * @return array<int, array<string, string>>
     */
    private function pemeriksaan(): array
    {
        $hasil = [];

        $hasil[] = config('app.debug')
            ? ['keadaan' => 'perlu tindakan', 'apa' => 'APP_DEBUG menyala', 'saran' => 'Di server produksi, nyalakan mode debug berarti setiap galat menampilkan isi berkas dan potongan konfigurasi kepada siapa pun yang membukanya. Setel APP_DEBUG=false.']
            : ['keadaan' => 'aman', 'apa' => 'Mode debug mati', 'saran' => 'Pesan galat tidak menampilkan rincian internal.'];

        $hasil[] = app()->environment('production')
            ? ['keadaan' => 'aman', 'apa' => 'Lingkungan produksi', 'saran' => 'Cache konfigurasi & rute sebaiknya dijalankan saat deploy.']
            : ['keadaan' => 'perlu diketahui', 'apa' => 'Bukan lingkungan produksi ('.app()->environment().')', 'saran' => 'Wajar saat pengembangan. Pastikan APP_ENV=production sudah disetel di server.'];

        $hasil[] = config('queue.default') === 'sync'
            ? ['keadaan' => 'aman', 'apa' => 'Antrean langsung (sync)', 'saran' => 'Tidak perlu pekerja antrean — cocok untuk free tier.']
            : ['keadaan' => 'perlu diketahui', 'apa' => 'Antrean '.config('queue.default'), 'saran' => 'Pastikan pekerja antrean benar-benar berjalan, kalau tidak email akan menumpuk tanpa terkirim.'];

        /*
         * Zona waktu.
         *
         * Seluruh jadwal dan tanggal rayon mengikuti WIB. Kalau server berjalan
         * di UTC, setiap tanggal bergeser tujuh jam: kegiatan yang berlangsung
         * pukul 20.00 tercatat keesokan harinya, dan pengingat yang seharusnya
         * pagi terkirim tengah malam. Tidak ada galat — hanya tanggal yang
         * salah, dan itu baru ketahuan berbulan-bulan kemudian.
         */
        $zona = (string) config('app.timezone');

        $hasil[] = $zona === 'Asia/Jakarta'
            ? ['keadaan' => 'aman', 'apa' => 'Zona waktu Asia/Jakarta', 'saran' => 'Tanggal dan jadwal mengikuti WIB.']
            : ['keadaan' => 'perlu diketahui', 'apa' => 'Zona waktu '.$zona, 'saran' => 'Jadwal rayon mengikuti WIB. Kalau ini server produksi, setel APP_TIMEZONE=Asia/Jakarta, kalau tidak setiap tanggal bergeser tujuh jam tanpa galat apa pun.'];

        $hasil[] = $this->penyimpanan()['cadangan_jumlah'] > 0
            ? ['keadaan' => 'aman', 'apa' => 'Cadangan tersedia', 'saran' => 'Unduh berkasnya dan simpan di luar server.']
            : ['keadaan' => 'perlu tindakan', 'apa' => 'Belum ada cadangan', 'saran' => 'Jalankan php artisan cadangan:buat, atau tunggu jadwal harian berjalan.'];

        $hasil[] = $this->antrean()['gagal'] > 0
            ? ['keadaan' => 'perlu tindakan', 'apa' => $this->antrean()['gagal'].' pekerjaan gagal', 'saran' => 'Periksa tujuannya di php artisan queue:failed. Pekerjaan yang gagal umumnya adalah email yang tidak terkirim.']
            : ['keadaan' => 'aman', 'apa' => 'Tidak ada pekerjaan gagal', 'saran' => 'Antrean bersih.'];

        return $hasil;
    }
}
