<?php

namespace Database\Seeders;

use App\Models\Period;
use Illuminate\Database\Seeder;

/**
 * Periode kepengurusan rayon sejak 2017 sampai sekarang.
 *
 * Tahun kepengurusan mengikuti tahun berjalan (bukan tahun ajaran), sehingga
 * periode terakhir dihitung dari tanggal hari ini.
 */
class PeriodSeeder extends Seeder
{
    private const TAHUN_AWAL = 2017;

    public function run(): void
    {
        $tahunSekarang = (int) now()->format('Y');

        for ($tahun = self::TAHUN_AWAL; $tahun <= $tahunSekarang; $tahun++) {
            Period::query()->updateOrCreate(
                ['tahun_mulai' => $tahun, 'tahun_selesai' => $tahun],
                [
                    'nama' => (string) $tahun,
                    'mulai' => $tahun.'-01-01',
                    'selesai' => $tahun.'-12-31',
                    'aktif' => $tahun === $tahunSekarang,
                    'urutan' => $tahun - self::TAHUN_AWAL + 1,
                ],
            );
        }

        $this->command?->info('Periode siap: '.self::TAHUN_AWAL.'–'.$tahunSekarang.' (aktif: '.$tahunSekarang.').');
    }
}
