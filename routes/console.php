<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Penjadwalan
|--------------------------------------------------------------------------
| Pengingat peminjaman dijalankan setiap pagi. Waktu 07.00 dipilih supaya
| pesannya sampai sebelum sekretariat dan kader memulai aktivitas hari itu.
|
| Sekaligus membersihkan antrian yang masa berlakunya sudah lewat, sehingga
| giliran berikutnya naik tanpa perlu ada yang membuka panel.
*/
Schedule::command('pinjaman:pengingat')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * Cadangan basis data harian.
 *
 * Dijalankan dini hari, saat hampir tidak ada yang memakai situs, supaya
 * pembacaan seluruh tabel tidak berebut dengan permintaan pengunjung. Yang
 * dibersihkan sekaligus adalah cadangan lama — penyimpanan gratis hampir
 * selalu terbatas, dan cadangan yang menumpuk sampai penuh akan menghentikan
 * pembuatan cadangan berikutnya.
 *
 * DI HOSTING TANPA CRON, jadwal ini tidak berjalan sendiri. Lihat
 * docs/panduan/deploy.md untuk endpoint penjadwal yang dipanggil dari luar.
 */
Schedule::command('cadangan:buat')
    ->dailyAt('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
