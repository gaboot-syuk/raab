<?php

namespace App\Console\Commands;

use App\Services\Cadangan;
use Illuminate\Console\Command;
use Throwable;

/**
 * Buat cadangan basis data.
 *
 * Dijalankan harian oleh penjadwal, dan bisa dijalankan sendiri oleh pengurus
 * kapan saja — terutama SEBELUM melakukan perubahan besar (impor data, migrasi,
 * pembersihan). Cadangan yang hanya dibuat otomatis sekali sehari tidak menolong
 * kalau perubahan besarnya dilakukan lima menit setelah jadwal itu lewat.
 */
class CadanganBuat extends Command
{
    protected $signature = 'cadangan:buat
                            {--simpan= : Jumlah berkas cadangan yang disimpan (bawaan dari layanan)}';

    protected $description = 'Membuat cadangan basis data dan membersihkan cadangan lama';

    public function handle(Cadangan $cadangan): int
    {
        try {
            $hasil = $cadangan->buat();
        } catch (Throwable $e) {
            $this->error('Cadangan gagal dibuat: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cadangan dibuat: '.$hasil['berkas']);
        $this->line('  Tabel : '.$hasil['tabel']);
        $this->line('  Baris : '.$hasil['baris']);
        $this->line('  Ukuran: '.number_format($hasil['ukuran'] / 1024, 1, ',', '.').' KB');

        $simpan = $this->option('simpan');
        $jumlah = $cadangan->bersihkan($simpan !== null ? (int) $simpan : Cadangan::SIMPAN_BERKAS);

        if ($jumlah > 0) {
            $this->line('  Cadangan lama dibersihkan: '.$jumlah.' berkas');
        }

        $this->newLine();
        $this->warn('Berkas cadangan memuat SELURUH isi basis data. Unduh lalu simpan di luar server,');
        $this->warn('karena berkas yang hanya ada di server yang sama tidak melindungi dari apa pun.');

        return self::SUCCESS;
    }
}
