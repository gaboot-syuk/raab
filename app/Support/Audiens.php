<?php

namespace App\Support;

use App\Models\Member;
use App\Models\User;

/**
 * Audiens: SATU-SATUNYA tempat aturan "siapa ini dan boleh membaca apa"
 * ditulis.
 *
 * Dipakai bersama oleh Pengumuman dan Arsip. Kalau aturannya ditulis dua kali
 * di dua tempat, cepat atau lambat keduanya akan berbeda — dan yang berbeda
 * itu biasanya yang lebih longgar.
 *
 * TANGGA AUDIENS (dari yang paling terbuka):
 *
 *   publik   — siapa pun, termasuk yang belum punya akun
 *   kader    — anggota berstatus aktif
 *   alumni   — anggota berstatus alumni
 *   pengurus — pemegang peran apa pun di kepengurusan
 *
 * ATURAN PENTING: **pengurus boleh membaca SEMUA audiens.** Bukan kelonggaran,
 * melainkan syarat supaya pekerjaan Sekretaris mungkin dilakukan: orang yang
 * mengelola arsip harus bisa memeriksa apa yang benar-benar dilihat kader
 * setelah ia mengubah hak aksesnya. Sebaliknya, kader TIDAK boleh membaca
 * dokumen khusus pengurus — inilah yang dijaga.
 */
final class Audiens
{
    public const PUBLIK = 'publik';

    public const KADER = 'kader';

    public const ALUMNI = 'alumni';

    public const PENGURUS = 'pengurus';

    /**
     * @var array<string, string>
     */
    public const PILIHAN = [
        self::PUBLIK => 'Umum (siapa saja)',
        self::KADER => 'Kader Aktif',
        self::ALUMNI => 'Alumni',
        self::PENGURUS => 'Pengurus',
    ];

    /**
     * Audiens yang dimiliki seorang penonton.
     *
     * Pengurus SELALU mendapat `pengurus`; kader/alumni mengikuti status
     * keanggotaannya. Penonton tanpa akun hanya `publik`.
     *
     * @return array<int, string>
     */
    public static function dimiliki(?User $pengguna): array
    {
        $dimiliki = [self::PUBLIK];

        if ($pengguna === null) {
            return $dimiliki;
        }

        $anggota = $pengguna->member;

        if ($anggota instanceof Member) {
            if ($anggota->status === Member::STATUS_AKTIF) {
                $dimiliki[] = self::KADER;
            }

            if ($anggota->status === Member::STATUS_ALUMNI) {
                $dimiliki[] = self::ALUMNI;
            }
        }

        if (method_exists($pengguna, 'roles') && $pengguna->roles->isNotEmpty()) {
            $dimiliki[] = self::PENGURUS;
        }

        return array_values(array_unique($dimiliki));
    }

    /**
     * Bolehkah penonton ini membaca sesuatu yang dibuka untuk `$akses`?
     *
     * @param  array<int, string>|null  $akses
     */
    public static function boleh(?array $akses, ?User $pengguna): bool
    {
        if ($pengguna !== null && self::pengurus($pengguna)) {
            return true;
        }

        return array_intersect($akses ?? [], self::dimiliki($pengguna)) !== [];
    }

    public static function pengurus(?User $pengguna): bool
    {
        return $pengguna !== null
            && method_exists($pengguna, 'roles')
            && $pengguna->roles->isNotEmpty();
    }

    /**
     * Buang nilai yang tidak dikenal, mis. sisa dari pilihan yang sudah dihapus.
     *
     * @param  array<int, mixed>  $nilai
     * @return array<int, string>
     */
    public static function bersihkan(array $nilai): array
    {
        return array_values(array_intersect(
            array_map('strval', $nilai),
            array_keys(self::PILIHAN),
        ));
    }
}
