<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Satu foto di dalam album.
 *
 * Foto dapat berasal dari Pustaka Media (`media_id`) atau URL luar (`url`).
 * Salah satu saja yang perlu terisi.
 */
#[Fillable(['gallery_id', 'media_id', 'url', 'keterangan', 'urutan'])]
class GalleryItem extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function galeri(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }

    /**
     * Baris Pustaka Media yang dipakai foto ini (bila ada).
     *
     * Memakai model Media milik spatie/laravel-medialibrary — sama seperti
     * slider dan sampul artikel, agar alamat gambarnya konsisten.
     */
    public function berkas(): ?Media
    {
        if (blank($this->media_id)) {
            return null;
        }

        return Media::query()->find($this->media_id);
    }

    /**
     * Alamat gambar yang dapat dipakai langsung pada atribut `src`.
     */
    public function sumber(bool $kecil = true): ?string
    {
        if (filled($this->url)) {
            return (string) $this->url;
        }

        $berkas = $this->berkas();

        if (! $berkas) {
            return null;
        }

        return $kecil && $berkas->hasGeneratedConversion('kecil')
            ? $berkas->getUrl('kecil')
            : $berkas->getUrl();
    }

    public function keteranganIsi(): string
    {
        return (string) ($this->getTranslation('keterangan', 'id', false) ?? '');
    }
}
