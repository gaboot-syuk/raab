<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Urutan penting: permission → role → Superadmin.
     *
     * Seeder ISI SITUS ikut di sini — bukan hanya yang dibutuhkan pengujian.
     * Sebelumnya hanya peran, akun, pengaturan, halaman, dan media sosial yang
     * dijalankan, sehingga basis data baru hasilnya: halaman /lso menjawab
     * "Belum ada data", halaman Periode dan Jabatan di panel tampil kosong,
     * dan penyusunan pengurus tidak bisa dimulai. Semua itu baru ketahuan
     * saat dipasang di hosting, karena lingkungan pengembangan mengisi
     * datanya sendiri lewat uji.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperadminSeeder::class,

            // Unit lebih dulu: jabatan menempel pada unit, dan periode
            // dibutuhkan sebelum penugasan dapat disusun.
            UnitSeeder::class,
            PeriodSeeder::class,
            PositionSeeder::class,

            SettingSeeder::class,
            PageSeeder::class,
            SocialLinkSeeder::class,
        ]);
    }
}
