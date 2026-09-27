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
 * Biro dan Lembaga Semi Otonom (LSO) di dalam rayon.
 *
 * Fase 2 hanya memakainya untuk mencatat asal unit kader. Halaman publik,
 * pengurus unit, galeri, dan agenda unit menyusul pada Fase 4.
 */
#[Fillable(['jenis', 'nama', 'slug', 'singkatan', 'deskripsi', 'warna', 'urutan', 'aktif'])]
class OrganisationUnit extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_BIRO = 'biro';
    public const JENIS_LSO = 'lso';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_BIRO => 'Biro',
        self::JENIS_LSO => 'Lembaga Semi Otonom',
    ];

    public array $translatable = ['deskripsi'];

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

    public function jabatan(): HasMany
    {
        return $this->hasMany(Position::class, 'unit_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(Member::class, 'unit_id');
    }

    public function galeri(): HasMany
    {
        return $this->hasMany(Gallery::class, 'unit_id');
    }

    public function agenda(): HasMany
    {
        return $this->hasMany(UnitAgenda::class, 'unit_id');
    }

    /**
     * Slug publik selalu dibuat otomatis.
     *
     * Diletakkan di model (bukan hanya di controller) karena unit juga dibuat
     * oleh seeder — dan halaman publik `/lso/{slug}` tidak boleh menerima unit
     * tanpa alamat. Inilah bug yang sempat membuat `/lso` gagal dibuka.
     */
    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            if (filled($unit->slug) || blank($unit->nama)) {
                return;
            }

            $unit->slug = self::slugUnik($unit->nama, $unit->id);
        });
    }

    /**
     * Slug unik dari nama unit, mis. "lso-jurnalistik" lalu "lso-jurnalistik-2".
     */
    public static function slugUnik(string $nama, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($nama);

        if ($dasar === '') {
            $dasar = 'unit';
        }

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

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeBiro(Builder $query): Builder
    {
        return $query->where('jenis', self::JENIS_BIRO)->orderBy('urutan');
    }

    public function scopeLso(Builder $query): Builder
    {
        return $query->where('jenis', self::JENIS_LSO)->orderBy('urutan');
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst($this->jenis);
    }
}
