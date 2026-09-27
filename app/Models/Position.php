<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jabatan dalam kepengurusan rayon.
 *
 * `level` dipakai menyusun bagan struktur (1 = pimpinan rayon, 2 = pengurus
 * inti, 3 = kepala biro/LSO), sedangkan `urutan` mengatur urutan tampil dalam
 * satu tingkat.
 */
#[Fillable(['nama', 'level', 'urutan', 'unit_id', 'rangkap_diizinkan', 'aktif'])]
class Position extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'urutan' => 'integer',
            'rangkap_diizinkan' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganisationUnit::class, 'unit_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true)->orderBy('level')->orderBy('urutan');
    }
}
