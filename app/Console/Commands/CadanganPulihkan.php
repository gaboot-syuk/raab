<?php

namespace App\Console\Commands;

use App\Services\Cadangan;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pulihkan basis data dari berkas cadangan.
 *
 * PERINTAH INI MENGHAPUS ISI TABEL YANG ADA. Karena itu ia menuntut penegasan
 * dengan mengetik ulang nama berkasnya, dan bukan sekadar menjawab "ya" —
 * jawaban "ya" bisa keluar dari jari yang refleks, sedangkan mengetik nama
 * berkas menuntut orangnya membaca nama itu lebih dulu.
 *
 * SEBELUM memulihkan, isi basis data saat ini dicadangkan lebih dulu. Memulihkan
 * dari berkas yang ternyata salah membuat keadaan sekarang hilang tanpa jejak,
 * dan itu justru masalah yang lebih besar daripada yang sedang diperbaiki.
 */
class CadanganPulihkan extends Command
{
    protected $signature = 'cadangan:pulihkan
                            {berkas : Nama berkas cadangan (lihat cadangan:daftar)}
                            {--force : Lewati penegasan — hanya untuk otomatisasi}
                            {--tanpa-cadangan-baru : Jangan cadangkan keadaan sekarang lebih dulu}';

    protected $description = 'Memulihkan basis data dari berkas cadangan (MENGHAPUS isi tabel yang ada)';

    public function handle(Cadangan $cadangan): int
    {
        $nama = (string) $this->argument('berkas');

        if (! $cadangan->ada($nama)) {
            $this->error('Berkas cadangan tidak ditemukan: '.$nama);
            $this->line('Cadangan yang tersedia:');

            foreach ($cadangan->daftar() as $berkas) {
                $this->line('  - '.$berkas['nama'].'  ('.$berkas['ukuran_teks'].', '.$berkas['dibuat'].')');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->warn('PERHATIAN: pemulihan akan MENGHAPUS isi seluruh tabel yang ada di basis data');
        $this->warn('sekarang, lalu mengisinya dari berkas "'.$nama.'".');
        $this->newLine();

        if (! $this->option('force')) {
            $jawaban = (string) $this->ask('Ketik ulang nama berkas untuk melanjutkan');

            if ($jawaban !== $nama) {
                $this->error('Nama berkas tidak cocok. Pemulihan dibatalkan, tidak ada yang berubah.');

                return self::FAILURE;
            }
        }

        if (! $this->option('tanpa-cadangan-baru')) {
            try {
                $aman = $cadangan->buat();
                $this->info('Keadaan sekarang dicadangkan lebih dulu: '.$aman['berkas']);
            } catch (Throwable $e) {
                $this->error('Gagal mencadangkan keadaan sekarang: '.$e->getMessage());
                $this->error('Pemulihan dibatalkan — lebih baik tidak memulihkan daripada kehilangan keduanya.');

                return self::FAILURE;
            }
        }

        try {
            $hasil = $cadangan->pulihkan($nama);
        } catch (Throwable $e) {
            $this->error('Pemulihan gagal: '.$e->getMessage());
            $this->error('Transaksi dibatalkan, jadi basis data seharusnya masih seperti semula.');

            return self::FAILURE;
        }

        $this->info('Pemulihan selesai: '.$hasil['tabel'].' tabel, '.$hasil['baris'].' baris.');

        foreach ($hasil['per_tabel'] as $tabel => $jumlah) {
            $this->line('  '.str_pad($tabel, 34).$jumlah.' baris');
        }

        $this->newLine();
        $this->warn('Jalankan `php artisan cache:clear` bila tampilan masih menunjukkan data lama —');
        $this->warn('pengaturan situs disimpan di cache dan tidak ikut dipulihkan.');

        return self::SUCCESS;
    }
}
