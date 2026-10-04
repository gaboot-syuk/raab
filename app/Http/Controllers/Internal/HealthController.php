<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\PemeriksaBasisData;
use Illuminate\Http\JsonResponse;

/**
 * Titik pemeriksaan kesehatan, untuk layanan pengawas dan penjaga tetap bangun.
 *
 * KENAPA ADA, PADAHAL LARAVEL SUDAH PUNYA /up
 *
 * `/up` bawaan Laravel menjawab 200 selama aplikasinya bisa dijalankan — dan
 * itu memang cukup untuk menjawab "wadahnya hidup?". Tetapi tidak lebih dari
 * itu: basis data yang mati, TLS yang ditolak, atau kredensial yang salah tidak
 * terlihat sama sekali dari sana. Situs bisa menjawab 200 di /up sambil
 * menampilkan halaman galat di setiap halaman yang sesungguhnya, dan pemantau
 * akan melaporkan semuanya baik-baik saja.
 *
 * Jalur ini menjawab pertanyaan yang lebih berguna: apakah aplikasi ini benar-
 * benar dapat melayani permintaan, termasuk menyentuh basis datanya.
 *
 * ALASANNYA TIDAK DIJELASKAN RINCI
 *
 * Jawabannya hanya menandai "terjangkau" atau "tidak". Rincian galat sambungan
 * — nama host, pengguna, pesan pengandar basis data — adalah bahan berharga
 * bagi siapa pun yang sedang memetakan sasaran, dan jalur ini terbuka tanpa
 * masuk. Rinciannya ada di log pengurus, tempat yang semestinya.
 *
 * KENAPA SELALU 200, BUKAN 503 SAAT BASIS DATA MATI
 *
 * Versi sebelumnya menjawab 503 saat basis data tidak terjangkau. Itu benar
 * secara semantik HTTP, tetapi salah secara operasional di sini: cron-job.org
 * dan layanan serupa menganggap setiap jawaban non-2xx sebagai kegagalan job.
 * Saat basis data gratis sedang bangun (dan itu normal terjadi beberapa kali
 * sehari), cron-job.org akan melaporkan "kesalahan HTTP" — padahal justru
 * cronjob itulah yang sedang menjalankan tugasnya: membangunkan basis data.
 *
 * Karena itu jawabannya kini selalu 200, dengan status sesungguhnya ada di
 * badan JSON. Pemantau uptime yang butuh membedakan "hidup" dan "hidup tapi
 * tidak bisa melayani" tetap bisa membacanya dari kolom `basis_data`.
 *
 * Kalau suatu saat butuh endpoint yang benar-benar menjawab 503 untuk pemantau
 * uptime ketat, buat jalur terpisah — misalnya /health/ketat — jangan ubah
 * jalur ini.
 */
class HealthController extends Controller
{
    public function __construct(private readonly PemeriksaBasisData $pemeriksa) {}

    public function __invoke(): JsonResponse
    {
        $basisDataTerjangkau = $this->pemeriksa->terjangkau();

        return response()->json([
            'status' => $basisDataTerjangkau ? 'ok' : 'terganggu',
            'basis_data' => $basisDataTerjangkau ? 'terjangkau' : 'tidak terjangkau',
            'waktu' => now()->toIso8601String(),
        ], 200);
    }
}