<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode kepengurusan rayon.
 *
 * Hanya satu periode yang aktif pada satu waktu. Mengganti periode aktif tidak
 * menghapus data periode lama (Fase 4).
 */
#[Fillable(['nama', 'tahun_mulai', 'tahun_selesai', 'mulai', 'selesai', 'aktif', 'urutan'])]
class Period extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai' => 'date',
            'selesai' => 'date',
            'aktif' => 'boolean',
            'tahun_mulai' => 'integer',
            'tahun_selesai' => 'integer',
        ];
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Periode yang sedang berjalan (bila ada).
     */
    public static function sedangAktif(): ?self
    {
        return self::query()->aktif()->first();
    }
}
