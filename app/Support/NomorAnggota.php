<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Pembuat nomor anggota rayon.
 *
 * Format: RAAB-{tahun}-{urut}  →  mis. RAAB-2026-001
 *
 * Nomor dihitung dari nomor terbesar pada tahun yang sama, di dalam transaksi
 * dengan penguncian baris, sehingga dua pendaftaran yang disetujui bersamaan
 * tidak mungkin mendapat nomor kembar.
 */
final class NomorAnggota
{
    public const AWALAN = 'RAAB';

    public const PANJANG_URUT = 3;

    public static function buat(?int $tahun = null): string
    {
        $tahun ??= (int) now()->format('Y');
        $awalan = self::AWALAN.'-'.$tahun.'-';

        return DB::transaction(function () use ($awalan): string {
            $terakhir = Member::withTrashed()
                ->where('nomor_anggota', 'like', $awalan.'%')
                ->orderByDesc('nomor_anggota')
                ->lockForUpdate()
                ->value('nomor_anggota');

            $urut = 1;

            if (is_string($terakhir) && str_starts_with($terakhir, $awalan)) {
                $urut = ((int) substr($terakhir, strlen($awalan))) + 1;
            }

            return $awalan.str_pad((string) $urut, self::PANJANG_URUT, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Tahun yang terkandung dalam sebuah nomor anggota.
     */
    public static function tahunDari(string $nomor): ?int
    {
        if (preg_match('/^'.self::AWALAN.'-(\d{4})-\d+$/', $nomor, $cocok) === 1) {
            return (int) $cocok[1];
        }

        return null;
    }
}
