<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use App\Support\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Identitas & parameter situs.
 *
 * Alamat, jam, peta, dan kontak sengaja diisi placeholder —
 * pengurus dapat menggantinya lewat Panel Pengurus tanpa menyentuh kode.
 * Kelompok perpustakaan memakai nilai bawaan dari docs (7 hari, 1x perpanjang,
 * 2 buku, 2x24 jam masa pengambilan, tanpa denda).
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $daftar = [
            // --- Identitas ---
            ['identitas', 'nama_rayon', 'PMII Rayon Ali Ahmad Baktsir', 'PMII Rayon Ali Ahmad Baktsir', 'teks', 'Nama rayon'],
            ['identitas', 'nama_singkat', 'PMII RAAB', 'PMII RAAB', 'teks', 'Nama singkat'],
            ['identitas', 'nama_komisariat', 'PMII Komisariat Raden Mas Said', 'Raden Mas Said Commissariat', 'teks', 'Nama komisariat'],
            ['identitas', 'nama_cabang', 'Cabang Sukoharjo', 'Sukoharjo Branch', 'teks', 'Nama cabang'],
            ['identitas', 'nama_kampus', 'UIN Raden Mas Said Surakarta', 'UIN Raden Mas Said Surakarta', 'teks', 'Nama kampus'],

            // --- Sekretariat (placeholder) ---
            ['sekretariat', 'alamat', 'Alamat sekretariat belum diisi', 'Secretariat address is not yet filled in', 'area', 'Alamat sekretariat'],
            ['sekretariat', 'jam_operasional', 'Senin–Jumat, 09.00–16.00 WIB', 'Monday–Friday, 09:00–16:00 (GMT+7)', 'teks', 'Jam operasional'],
            ['sekretariat', 'peta_embed', '', '', 'area', 'Kode sematan Google Maps'],
            ['sekretariat', 'email', 'sekretariat@raab.test', 'sekretariat@raab.test', 'teks', 'Email resmi'],
            ['sekretariat', 'telepon', '08xx-xxxx-xxxx', '08xx-xxxx-xxxx', 'teks', 'Nomor telepon'],
            ['sekretariat', 'whatsapp', '08xx-xxxx-xxxx', '08xx-xxxx-xxxx', 'teks', 'Nomor WhatsApp'],

            // --- SEO ---
            ['seo', 'seo_judul', 'PMII Rayon Ali Ahmad Baktsir — Komisariat Raden Mas Said', 'PMII Rayon Ali Ahmad Baktsir — Raden Mas Said Commissariat', 'teks', 'Judul SEO bawaan'],
            ['seo', 'seo_deskripsi', 'Situs resmi PMII Rayon Ali Ahmad Baktsir, Komisariat Raden Mas Said, Cabang Sukoharjo, UIN Raden Mas Said Surakarta.', 'Official website of PMII Rayon Ali Ahmad Baktsir, Raden Mas Said Commissariat, Sukoharjo Branch.', 'area', 'Deskripsi SEO bawaan'],

            // --- Parameter perpustakaan ---
            ['perpustakaan', 'masa_pinjam_hari', '7', null, 'angka', 'Masa pinjam buku (hari)'],
            ['perpustakaan', 'maks_buku_per_peminjam', '2', null, 'angka', 'Jumlah buku maksimal per peminjam'],
            ['perpustakaan', 'maks_perpanjangan', '1', null, 'angka', 'Maksimal perpanjangan'],
            ['perpustakaan', 'masa_ambil_jam', '48', null, 'angka', 'Masa berlaku buku siap diambil (jam)'],
            ['perpustakaan', 'denda_aktif', '0', null, 'boolean', 'Denda keterlambatan (0 = tanpa denda)'],

            // --- Tampilan ---
            ['tampilan', 'tema_bawaan', 'sistem', null, 'teks', 'Tema bawaan pengunjung (terang/gelap/sistem)'],
            ['tampilan', 'pita_teks', 'Pendaftaran Mapaba & PKD dibuka', 'Mapaba & PKD registration is open', 'teks', 'Teks pita berjalan di beranda'],
            ['tampilan', 'catatan_placeholder', 'Bagian ini masih memakai data contoh dan akan diperbarui oleh pengurus.', 'This section still uses sample data and will be updated by the board.', 'area', 'Pemberitahuan data contoh'],

            // --- Statistik beranda ---
            // Angka kader aktif diperbarui otomatis mulai Fase 2 (keanggotaan).
            ['statistik', 'stat_anggota', '0', null, 'angka', 'Jumlah kader aktif (otomatis mulai Fase 2)'],
            ['statistik', 'stat_lso', '5', null, 'angka', 'Jumlah Lembaga Semi Otonom'],
            ['statistik', 'stat_biro', '8', null, 'angka', 'Jumlah biro'],
            ['statistik', 'stat_sejak', '2017', null, 'angka', 'Tahun berdiri'],

            /*
             * --- Terjemahan otomatis ---
             * Kunci API sengaja TIDAK disimpan di sini (lihat Penerjemah::kunci());
             * yang dapat diatur pengurus hanya pilihan penyedianya.
             */
            ['terjemahan', 'penerjemah_driver', 'none', null, 'pilihan', 'Penyedia penerjemah otomatis'],

            // Parameter peminjaman — dipakai App\Models\Loan dan layanan
            // Peminjaman, sehingga pengurus dapat mengubahnya tanpa mengubah kode.
            ['perpustakaan', 'pinjaman_masa_hari', '7', null, 'angka', 'Masa pinjam bawaan (hari)'],
            ['perpustakaan', 'pinjaman_perpanjangan_maks', '1', null, 'angka', 'Maksimal perpanjangan per peminjaman'],
        ];

        foreach ($daftar as [$grup, $kunci, $nilaiId, $nilaiEn, $tipe, $label]) {
            $setting = SiteSetting::query()->updateOrCreate(
                ['kunci' => $kunci],
                [
                    'grup' => $grup,
                    'nilai' => $nilaiId,
                    'tipe' => $tipe,
                    'label' => $label,
                    'publik' => true,
                ],
            );

            if ($nilaiEn !== null && $nilaiEn !== '') {
                $setting->setTranslation('nilai', 'en', $nilaiEn);
                $setting->save();
            }
        }

        Pengaturan::lupakan();

        // Daftar pilihan untuk pengaturan bertipe dropdown (lihat kolom `pilihan`).
        SiteSetting::query()
            ->where('kunci', 'penerjemah_driver')
            ->first()
            ?->forceFill(['pilihan' => [
                \App\Services\Penerjemah::DRIVER_NONE,
                \App\Services\Penerjemah::DRIVER_DEEPL,
                \App\Services\Penerjemah::DRIVER_GOOGLE,
            ]])
            ->save();

        $this->command?->info('Pengaturan situs siap: '.count($daftar).' kunci.');
    }
}
