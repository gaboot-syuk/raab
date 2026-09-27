<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Kategori prestasi (mis. "Akademik", "Olahraga", "Organisasi").
 *
 * Sama polanya dengan kategori artikel dan kategori iuran: dinonaktifkan tanpa
 * dihapus, supaya prestasi lama yang memakainya tidak kehilangan kategorinya.
 */
#[Fillable([
    'kode',
    'nama',
    'keterangan',
    'urutan',
    'aktif',
])]
class AchievementCategory extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['nama', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function prestasi(): HasMany
    {
        return $this->hasMany(Achievement::class, 'achievement_category_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('id');
    }

    public function namaTeks(): string
    {
        return $this->getTranslation('nama', 'id') ?: 'Kategori';
    }
}
