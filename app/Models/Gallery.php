<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Album foto unit atau galeri rayon.
 *
 * Dipakai halaman publik `/lso/{slug}` dan `/galeri`. Album lama tidak dihapus
 * saat unit dinonaktifkan — hanya kehilangan kaitannya.
 */
#[Fillable(['unit_id', 'judul', 'slug', 'deskripsi', 'tanggal', 'lokasi', 'publik', 'urutan'])]
class Gallery extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['judul', 'slug', 'deskripsi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'publik' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'unit_id');
    }

    public function item(): HasMany
    {
        return $this->hasMany(GalleryItem::class)->orderBy('urutan');
    }

    public function scopePublik(Builder $query): Builder
    {
        return $query->where('publik', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderByDesc('tanggal')->orderByDesc('id');
    }

    /**
     * Jumlah item foto — dipakai daftar panel tanpa memuat seluruh item.
     */
    public function jumlahItem(): int
    {
        return $this->item()->count();
    }
}
