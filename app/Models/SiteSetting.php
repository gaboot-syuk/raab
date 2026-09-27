<?php

namespace App\Models;

use App\Support\Pengaturan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable(['grup', 'kunci', 'nilai', 'tipe', 'pilihan', 'label', 'keterangan', 'publik'])]
class SiteSetting extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const TIPE_TEKS = 'teks';

    public const TIPE_AREA = 'area';

    public const TIPE_ANGKA = 'angka';

    public const TIPE_BOOLEAN = 'boolean';

    public const TIPE_URL = 'url';

    public const TIPE_GAMBAR = 'gambar';

    /** Pengaturan berupa dropdown; daftar nilai sah disimpan di kolom `pilihan`. */
    public const TIPE_PILIHAN = 'pilihan';

    public array $translatable = ['nilai'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publik' => 'boolean',
            'pilihan' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Cache pengaturan dibuang setiap kali ada perubahan.
        static::saved(fn () => Pengaturan::lupakan());
        static::deleted(fn () => Pengaturan::lupakan());
    }

    public function sebagaiBoolean(): bool
    {
        return filter_var($this->nilai ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function sebagaiAngka(): int|float
    {
        $nilai = $this->nilai ?? 0;

        return is_numeric($nilai) ? $nilai + 0 : 0;
    }
}
