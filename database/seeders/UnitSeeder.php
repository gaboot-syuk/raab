<?php

namespace Database\Seeders;

use App\Models\OrganisationUnit;
use Illuminate\Database\Seeder;

/**
 * 8 Biro dan 5 Lembaga Semi Otonom.
 *
 * Daftar ini berasal dari struktur rayon yang disepakati pengurus. Nama LSO
 * memakai singkatan yang berlaku sehari-hari; nama panjangnya ada di kolom
 * deskripsi versi bahasa Indonesia.
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $biro = [
            ['Advoger', 'ADV', 'Advokasi dan gerakan: pendampingan, kampanye, dan isu kebijakan publik.', 'primary'],
            ['Kaderisasi', 'KDR', 'Pendidikan kader: Mapaba, PKD, dan pendampingan kader baru.', 'accent'],
            ['Keilmuan', 'ILM', 'Diskusi ilmiah, riset, dan pendampingan akademik kader.', 'primary-500'],
            ['Keagamaan', 'AGA', 'Kajian keislaman, peringatan hari besar, dan pembinaan spiritual.', 'success'],
            ['Gender', 'GEN', 'Isu gender, kesetaraan, dan perlindungan kelompok rentan.', 'danger'],
            ['Kebudayaan', 'BUD', 'Seni, tradisi, dan ekspresi budaya kader.', 'primary-800'],
            ['Media', 'MED', 'Pengelolaan kanal informasi, desain, dan publikasi rayon.', 'accent-600'],
            ['Kewirausahaan', 'WIR', 'Usaha kader, kemandirian finansial, dan pengembangan bakat wirausaha.', 'primary'],
        ];

        $lso = [
            ['Mutasi', 'MTS', 'Ruang seni kader: musik, teater, tari, dan pertunjukan aksi.', 'primary'],
            ['Harokatuna', 'HRK', 'Seni religi: hadrah dan pembinaan majelis.', 'accent'],
            ['Lembaga Dakwah Rayon', 'LDR', 'Dakwah kampus, kajian rutin, dan pendampingan keagamaan.', 'success'],
            ['LPM Albiruni', 'LPM', 'Pers mahasiswa: jurnalistik, riset liputan, dan penerbitan.', 'danger'],
            ['MJT', 'MJT', 'Publikasi dan dokumentasi kegiatan rayon.', 'primary-800'],
        ];

        foreach ([[OrganisationUnit::JENIS_BIRO, $biro], [OrganisationUnit::JENIS_LSO, $lso]] as [$jenis, $daftar]) {
            foreach ($daftar as $urutan => [$nama, $singkatan, $deskripsi, $warna]) {
                OrganisationUnit::query()->updateOrCreate(
                    ['jenis' => $jenis, 'nama' => $nama],
                    [
                        'singkatan' => $singkatan,
                        'deskripsi' => ['id' => $deskripsi],
                        'warna' => $warna,
                        'urutan' => $urutan + 1,
                        'aktif' => true,
                    ],
                );
            }
        }

        $this->command?->info('Unit organisasi siap: '.count($biro).' biro, '.count($lso).' LSO.');
    }
}
