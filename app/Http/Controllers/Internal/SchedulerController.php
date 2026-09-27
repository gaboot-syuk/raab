<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Titik pemicu penjadwal untuk hosting TANPA cron.
 *
 * Hosting gratis umumnya tidak menyediakan cron. Tanpa cron, `schedule:run`
 * tidak pernah dipanggil, dan semua pekerjaan terjadwal berhenti tanpa gejala:
 * pengingat peminjaman tidak terkirim, cadangan harian tidak pernah dibuat.
 * Tidak ada galat, tidak ada peringatan — hanya keheningan.
 *
 * Jalur ini menjadi penggantinya: layanan penjadwal gratis memanggil alamat ini
 * secara berkala, dan perintah yang sudah waktunya dijalankan.
 *
 * KENAPA PAKAI TOKEN, BUKAN TANPA APA-APA:
 * alamat ini menjalankan perintah. Kalau siapa pun bisa memanggilnya, siapa pun
 * bisa memicu pembuatan cadangan berulang kali sampai ruang penyimpanan penuh —
 * atau memanggil perintah berat sesuka hati. Tokennya karena itu wajib, dan
 * dibandingkan dengan `hash_equals` supaya tidak bisa ditebak lewat waktu balasan.
 *
 * Kalau `SCHEDULER_TOKEN` belum diisi, jalur ini MENJAWAB 404 — bukan berjalan
 * tanpa perlindungan. Fitur yang tidak dikonfigurasi harus mati, bukan terbuka.
 */
class SchedulerController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $rahasia = (string) config('services.scheduler.token');

        if ($rahasia === '') {
            abort(404);
        }

        if (! hash_equals($rahasia, $token)) {
            abort(403);
        }

        $jadwal = app(Schedule::class);
        $dijalankan = [];
        $galat = [];

        foreach ($jadwal->events() as $peristiwa) {
            if (! $peristiwa->isDue(app())) {
                continue;
            }

            $nama = $peristiwa->description ?: (string) ($peristiwa->command ?? 'perintah');

            try {
                $peristiwa->run(app());
                $dijalankan[] = $nama;
            } catch (\Throwable $e) {
                // Satu perintah yang gagal tidak boleh menghentikan sisanya —
                // kalau tidak, kegagalan cadangan akan ikut menahan pengingat.
                $galat[$nama] = $e->getMessage();
                Log::error('Penjadwal terjadwal gagal dijalankan.', [
                    'perintah' => $nama,
                    'galat' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'dijalankan' => $dijalankan,
            'galat' => $galat,
            'waktu' => now()->toIso8601String(),
        ], $galat === [] ? 200 : 500);
    }
}
