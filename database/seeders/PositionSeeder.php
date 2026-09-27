<?php

namespace Database\Seeders;

use App\Models\OrganisationUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Jabatan kepengurusan rayon.
 *
 * Level 1 = pimpinan rayon, level 2 = pengurus inti, level 3 = kepala biro/LSO.
 * Jabatan kepala biro dibuat otomatis untuk setiap biro & LSO yang ada, sehingga
 * menambah unit baru cukup diikuti satu perintah seeder ini.
 *
 * CATATAN: daftar jabatan masih menunggu penyesuaian dari pengurus
 * (lihat docs/07-asumsi-terbuka.md). Ubah daftar di bawah bila nama jabatan
 * resmi berbeda.
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $inti = [
            ['Mabinra', 1, 1],
            ['Ketua Rayon', 1, 2],
            ['Wakil Ketua Rayon', 1, 3],
            ['Sekretaris 1', 2, 1],
            ['Sekretaris 2', 2, 2],
            ['Bendahara 1', 2, 3],
            ['Bendahara 2', 2, 4],
        ];

        foreach ($inti as [$nama, $level, $urutan]) {
            Position::query()->updateOrCreate(
                ['nama' => $nama, 'unit_id' => null],
                ['level' => $level, 'urutan' => $urutan, 'aktif' => true],
            );
        }

        // Kepala biro & ketua LSO dibuat mengikuti daftar unit yang aktif.
        $unit = OrganisationUnit::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get();

        foreach ($unit as $urutan => $satuan) {
            $nama = $satuan->jenis === OrganisationUnit::JENIS_BIRO
                ? 'Kepala Biro '.$satuan->nama
                : 'Ketua '.$satuan->nama;

            Position::query()->updateOrCreate(
                ['nama' => $nama, 'unit_id' => $satuan->id],
                ['level' => 3, 'urutan' => $urutan + 1, 'aktif' => true],
            );
        }

        $this->command?->info('Jabatan siap: '.count($inti).' jabatan inti + '.$unit->count().' jabatan unit.');
    }
}
