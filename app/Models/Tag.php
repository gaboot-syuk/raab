<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable(['nama', 'slug', 'dipakai'])]
class Tag extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['nama', 'slug'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dipakai' => 'integer',
        ];
    }

    public function artikel(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Tag yang benar-benar dipakai, diurutkan dari yang paling sering.
     */
    public function scopePopuler(Builder $query): Builder
    {
        return $query->where('dipakai', '>', 0)->orderByDesc('dipakai');
    }
}
