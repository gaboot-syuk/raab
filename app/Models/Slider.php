<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'judul',
    'subjudul',
    'label_tombol',
    'tautan_tombol',
    'media_id',
    'urutan',
    'aktif',
    'mulai_pada',
    'berakhir_pada',
    'dibuat_oleh',
])]
class Slider extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['judul', 'subjudul', 'label_tombol'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
            'mulai_pada' => 'datetime',
            'berakhir_pada' => 'datetime',
        ];
    }

    /**
     * Slider yang boleh tampil saat ini.
     */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('aktif', true)
            ->where(fn (Builder $q) => $q->whereNull('mulai_pada')->orWhere('mulai_pada', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('berakhir_pada')->orWhere('berakhir_pada', '>=', now()))
            ->orderBy('urutan');
    }
}
