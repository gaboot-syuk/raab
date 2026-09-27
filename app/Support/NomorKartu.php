<?php

namespace App\Support;

use App\Models\MemberCard;
use Illuminate\Support\Facades\DB;

/**
 * Pembuat nomor kartu kader.
 *
 * Format: K-RAAB-{tahun}-{urut}  →  mis. K-RAAB-2026-003
 *
 * KENAPA KARTU PUNYA URUTAN SENDIRI, TIDAK IKUT NOMOR ANGGOTA.
 *
 * Sebelum ini, nomor kartu diambil dari nomor anggota: "K-" + nomor anggota.
 * Terdengar rapi, tetapi salah — karena keduanya punya batas KEUNIKAN
 * masing-masing, sementara hanya satu yang punya urutan.
 *
 * Kegagalannya nyata dan pernah terjadi: satu anggota menerima kartu ketika
 * nomor anggotanya belum terbit. Nomor yang dipakai untuk kartunya itu dihitung
 * dari urutan nomor anggota, TETAPI TIDAK DISIMPAN ke anggota tersebut. Urutan
 * anggota dan urutan kartu pun berbeda satu langkah. Anggota berikutnya yang
 * disetujui mendapat nomor anggota yang sama dengan nomor yang sudah terpakai di
 * kartu — dan persetujuannya GAGAL total dengan galat batas unik.
 *
 * Yang dilihat Sekretaris saat itu: menekan "Ya, setujui", lalu tidak terjadi
 * apa-apa. Pengajuannya tetap menunggu, tanpa satu pun keterangan.
 *
 * Karena itu nomor kartu dihitung dari urutannya SENDIRI, persis seperti
 * NomorAnggota: nomor terbesar pada tahun yang sama, ditambah satu, di dalam
 * transaksi dengan penguncian baris.
 */
final class NomorKartu
{
    public const AWALAN = 'K-RAAB';

    public const PANJANG_URUT = 3;

    public static function buat(?int $tahun = null): string
    {
        $tahun ??= (int) now()->format('Y');
        $awalan = self::AWALAN.'-'.$tahun.'-';

        return DB::transaction(function () use ($awalan): string {
            $terakhir = MemberCard::query()
                ->where('nomor_kartu', 'like', $awalan.'%')
                ->orderByDesc('nomor_kartu')
                ->lockForUpdate()
                ->value('nomor_kartu');

            $urut = 1;

            if (is_string($terakhir) && str_starts_with($terakhir, $awalan)) {
                $urut = ((int) substr($terakhir, strlen($awalan))) + 1;
            }

            return $awalan.str_pad((string) $urut, self::PANJANG_URUT, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Tahun yang terkandung dalam sebuah nomor kartu.
     */
    public static function tahunDari(string $nomor): ?int
    {
        if (preg_match('/^K-RAAB-(\d{4})-\d+$/', $nomor, $cocok) === 1) {
            return (int) $cocok[1];
        }

        return null;
    }
}
