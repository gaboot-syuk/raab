<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Pembaca pengaturan situs dengan cache.
 *
 * Dipakai layout publik (footer, kontak, SEO) supaya nilai identitas rayon
 * hanya diambil sekali per permintaan, dan tidak perlu di-hardcode.
 */
class Pengaturan
{
    public const CACHE_KEY = 'pengaturan-situs';

    /**
     * Seluruh pengaturan dalam bentuk pasangan kunci => nilai
     * (nilai sudah mengikuti bahasa aktif).
     *
     * @return array<string, mixed>
     */
    public static function semua(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return SiteSetting::query()
                ->get()
                ->mapWithKeys(fn (SiteSetting $baris): array => [$baris->kunci => $baris->nilai])
                ->all();
        });
    }

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        return self::semua()[$kunci] ?? $bawaan;
    }

    public static function teks(string $kunci, string $bawaan = ''): string
    {
        $nilai = self::ambil($kunci, $bawaan);

        return is_scalar($nilai) ? (string) $nilai : $bawaan;
    }

    public static function angka(string $kunci, int $bawaan = 0): int
    {
        $nilai = self::ambil($kunci, $bawaan);

        return is_numeric($nilai) ? (int) $nilai : $bawaan;
    }

    public static function boolean(string $kunci, bool $bawaan = false): bool
    {
        $nilai = self::ambil($kunci);

        return $nilai === null ? $bawaan : filter_var($nilai, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Buang cache — dipanggil otomatis saat pengaturan berubah.
     */
    public static function lupakan(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
