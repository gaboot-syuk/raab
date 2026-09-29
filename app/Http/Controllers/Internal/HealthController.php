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
 */
class HealthController extends Controller
{
    public function __construct(private readonly PemeriksaBasisData $pemeriksa) {}

    public function __invoke(): JsonResponse
    {
        $basisDataTerjangkau = $this->pemeriksa->terjangkau();

        /*
         * Basis data yang mati dijawab 503, bukan 200.
         *
         * Bagi layanan penjaga tetap bangun, angka statusnya tidak penting —
         * yang penting ada permintaan yang masuk. Tetapi bagi pemantau uptime,
         * membedakan "hidup" dan "hidup tapi tidak bisa melayani" adalah
         * seluruh gunanya.
         */
        return response()->json([
            'status' => $basisDataTerjangkau ? 'ok' : 'terganggu',
            'basis_data' => $basisDataTerjangkau ? 'terjangkau' : 'tidak terjangkau',
            'waktu' => now()->toIso8601String(),
        ], $basisDataTerjangkau ? 200 : 503);
    }
}
