<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Kategori aset inventaris.
 */
#[Fillable(['nama', 'slug', 'urutan', 'aktif'])]
class InventoryCategory extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['nama'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function aset(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function namaTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('nama', $bahasa, false)
            ?: $this->getTranslation('nama', 'id', false)
            ?: '—');
    }

    public static function slugUnik(string $nama, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($nama) ?: 'kategori';

        $slug = $dasar;
        $urutan = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$urutan;
            $urutan++;
        }

        return $slug;
    }
}
