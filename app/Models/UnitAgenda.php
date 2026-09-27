<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Agenda publik unit (biro & LSO).
 */
#[Fillable(['unit_id', 'gambar_media_id', 'judul', 'deskripsi', 'mulai', 'selesai', 'lokasi', 'publik'])]
class UnitAgenda extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['judul', 'deskripsi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
            'publik' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'unit_id');
    }

    public function scopePublik(Builder $query): Builder
    {
        return $query->where('publik', true);
    }

    /**
     * Agenda yang belum lewat — yang tampil di bagian "Akan Datang".
     */
    public function scopeMendatang(Builder $query): Builder
    {
        return $query->where('mulai', '>=', now())->orderBy('mulai');
    }

    public function scopeLampau(Builder $query): Builder
    {
        return $query->where('mulai', '<', now())->orderByDesc('mulai');
    }
}
