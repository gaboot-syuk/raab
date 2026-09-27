<?php

namespace App\Console\Commands;

use App\Services\Cadangan;
use Illuminate\Console\Command;

/**
 * Daftar berkas cadangan yang ada di server.
 *
 * Dipisah dari `cadangan:buat` supaya orang yang hendak memulihkan bisa melihat
 * persis nama berkas apa yang tersedia — nama berkas harus DIKETIK ULANG saat
 * memulihkan, dan mengetiknya dari ingatan adalah cara paling mudah salah.
 */
class CadanganDaftar extends Command
{
    protected $signature = 'cadangan:daftar {--pulihkan-berkas= : Tampilkan perintah pemulihan untuk berkas tertentu}';

    protected $description = 'Menampilkan berkas cadangan yang tersimpan di server';

    public function handle(Cadangan $cadangan): int
    {
        $daftar = $cadangan->daftar();

        if ($daftar === []) {
            $this->warn('Belum ada berkas cadangan di server.');
            $this->line('Buat satu dengan: php artisan cadangan:buat');

            return self::SUCCESS;
        }

        $this->table(
            ['Nama berkas', 'Ukuran', 'Dibuat'],
            array_map(fn (array $b): array => [$b['nama'], $b['ukuran_teks'], $b['dibuat']], $daftar),
        );

        $this->line('Total: '.count($daftar).' berkas. Menyimpan '.Cadangan::SIMPAN_BERKAS.' terbaru.');

        if ($this->option('pulihkan-berkas') !== null) {
            $this->newLine();
            $this->line('Untuk memulihkan:');
            $this->line('  php artisan cadangan:pulihkan '.$this->option('pulihkan-berkas'));
        }

        $this->newLine();
        $this->warn('Cadangan yang hanya ada di server ini belum melindungi apa pun.');
        $this->warn('Unduh berkasnya dan simpan di luar server.');

        return self::SUCCESS;
    }
}
