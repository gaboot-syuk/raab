<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menunggu basis data siap sebelum melanjutkan.
 *
 * KENAPA ADA
 *
 * Basis data gratis (Aiven free tier, misalnya) dimatikan otomatis saat
 * tidak ada aktivitas, dan saat bangun kembali hostname-nya sempat tidak
 * dapat di-resolve selama beberapa detik sampai beberapa menit.
 *
 * Pekerja antrean yang dijalankan langsung tanpa menunggu akan gagal
 * seketika, dan supervisor — yang tugasnya menyalakan ulang proses yang
 * mati — akan mengulanginya setiap satu detik tanpa henti. Lognya penuh
 * jejak tumpukan, CPU terpakai untuk sesuatu yang tidak berguna, dan
 * basis datanya tidak menjadi lebih cepat siap.
 *
 * Perintah ini menjadi penengah: ia mencoba menyentuh basis data berulang
 * kali dengan jeda dua detik, sampai berhasil atau sampai batas percobaan
 * habis. Dipakai di supervisord sebagai langkah pertama sebelum pekerja
 * antrean benar-benar dijalankan.
 *
 * Kenapa dua detik, bukan satu atau lima: satu detik terlalu rapat dan
 * membuat log berisik saat basis data butuh waktu; lima detik membuat
 * pemulihan terasa lambat padahal basis data biasanya siap dalam hitungan
 * detik. Dua detik adalah kompromi yang wajar.
 */
class TungguBasisData extends Command
{
    protected $signature = 'db:tunggu
                            {--maks=60 : Batas percobaan, masing-masing berjarak dua detik}';

    protected $description = 'Menunggu basis data siap sebelum melanjutkan';

    public function handle(): int
    {
        $maks = max(1, (int) $this->option('maks'));

        for ($i = 1; $i <= $maks; $i++) {
            try {
                DB::connection()->getPdo();

                $this->info(sprintf(
                    'Basis data siap pada percobaan %d dari %d.',
                    $i,
                    $maks,
                ));

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->warn(sprintf(
                    'Percobaan %d/%d: belum siap — %s',
                    $i,
                    $maks,
                    $e->getMessage(),
                ));

                if ($i < $maks) {
                    sleep(2);
                }
            }
        }

        $this->error(sprintf(
            'Basis data tidak siap setelah %d percobaan (%d detik).',
            $maks,
            $maks * 2,
        ));

        return self::FAILURE;
    }
}