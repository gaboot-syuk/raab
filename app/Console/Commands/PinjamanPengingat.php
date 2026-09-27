<?php

namespace App\Console\Commands;

use App\Services\Peminjaman;
use Illuminate\Console\Command;

/**
 * Pengingat peminjaman & pembersihan antrian kedaluwarsa.
 *
 * Dijalankan terjadwal (lihat routes/console.php). Dipisah dari scheduler
 * supaya dapat dijalankan manual oleh pengurus kapan saja — misalnya setelah
 * sekretariat tutup beberapa hari.
 */
class PinjamanPengingat extends Command
{
    protected $signature = 'pinjaman:pengingat
                            {--lewati-antrian : Lewati antrian yang masa berlakunya sudah lewat}';

    protected $description = 'Kirim pengingat H-1 & keterlambatan, dan bersihkan antrian yang kedaluwarsa';

    public function handle(Peminjaman $peminjaman): int
    {
        $h1 = $peminjaman->kirimPengingatH1();
        $terlambat = $peminjaman->kirimPengingatTerlambat();
        $dilewati = $peminjaman->lewatiKedaluwarsa();

        $this->info(sprintf(
            'Pengingat H-1: %d · Pengingat terlambat: %d · Antrian dilewati: %d',
            $h1,
            $terlambat,
            $dilewati,
        ));

        return self::SUCCESS;
    }
}
