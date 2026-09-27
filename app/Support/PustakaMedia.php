<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gambar dari pustaka media, untuk formulir dan tampilan panel.
 *
 * Dikumpulkan di satu tempat karena beberapa formulir membutuhkannya — poster
 * Event, gambar agenda unit, dan nanti sampul artikel. Sebelumnya tiap
 * pengendali menyalin logikanya sendiri, dan salinan itu mulai berbeda:
 * ukuran konversi yang dipakai tidak lagi sama, dan yang paling muda tidak
 * menyaring `mime_type` sama sekali sehingga berkas PDF ikut muncul di
 * pemilih gambar.
 */
final class PustakaMedia
{
    /** Ukuran konversi untuk daftar pilihan — kecil supaya ringan dimuat. */
    private const UKURAN_DAFTAR = 'kecil';

    /**
     * Daftar gambar untuk pemilih di formulir panel.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function pilihan(int $batas = 200): Collection
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit($batas)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'nama' => $media->name ?: $media->file_name,
                'url' => self::tautan($media),
            ]);
    }

    /**
     * Peta `id media → URL`, dikumpulkan dengan SATU kueri.
     *
     * Dipakai saat menampilkan banyak baris sekaligus (daftar agenda). Memanggil
     * `url()` per baris akan menghasilkan satu kueri per agenda — persis
     * masalah N+1 yang sudah pernah menggigit di halaman lain.
     *
     * @param  iterable<int|null>  $mediaIds
     * @return array<int, string>
     */
    public static function peta(iterable $mediaIds, string $ukuran = self::UKURAN_DAFTAR): array
    {
        $id = collect($mediaIds)->filter()->unique()->values();

        if ($id->isEmpty()) {
            return [];
        }

        return Media::query()
            ->whereIn('id', $id)
            ->get()
            ->mapWithKeys(fn (Media $media): array => [$media->id => self::tautan($media, $ukuran)])
            ->all();
    }

    /**
     * URL satu gambar, atau null bila belum dipilih / berkasnya sudah hilang.
     *
     * Berkas yang hilang sengaja menghasilkan null, bukan galat: agenda lama
     * harus tetap dapat dibuka meski gambarnya sudah dihapus dari pustaka.
     */
    public static function url(?int $mediaId, string $ukuran = self::UKURAN_DAFTAR): ?string
    {
        if (blank($mediaId)) {
            return null;
        }

        $media = Media::query()->find($mediaId);

        return $media ? self::tautan($media, $ukuran) : null;
    }

    /**
     * Pakai konversi bila sudah tergenerate; kalau belum, jatuh ke berkas asli
     * supaya gambarnya tetap tampil.
     */
    private static function tautan(Media $media, string $ukuran = self::UKURAN_DAFTAR): string
    {
        return $media->hasGeneratedConversion($ukuran)
            ? $media->getUrl($ukuran)
            : $media->getUrl();
    }
}
