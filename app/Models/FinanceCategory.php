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
 * Kategori keuangan — masuk atau keluar, dan boleh BERTINGKAT.
 *
 * Contoh: "Belanja" (induk) › "Belanja ATK" (anak). Laporan rekap kategori
 * menjumlahkan anak ke induknya, jadi transaksi boleh ditempelkan di tingkat
 * mana pun.
 *
 * Jenis dipisah tegas: kategori masuk tidak boleh dipakai transaksi keluar.
 */
#[Fillable([
    'parent_id',
    'kode',
    'nama',
    'keterangan',
    'jenis',
    'urutan',
    'aktif',
])]
class FinanceCategory extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_MASUK = 'masuk';

    public const JENIS_KELUAR = 'keluar';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_MASUK => 'Penerimaan',
        self::JENIS_KELUAR => 'Pengeluaran',
    ];

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

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan')->orderBy('kode');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'category_id');
    }

    public function anggaran(): HasMany
    {
        return $this->hasMany(Budget::class, 'category_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeJenis(Builder $query, string $jenis): Builder
    {
        return $query->where('jenis', $jenis);
    }

    /** Hanya kategori induk (tanpa parent). */
    public function scopeInduk(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function namaTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('nama', $bahasa, false)
            ?: $this->getTranslation('nama', 'id', false)
            ?: $this->kode);
    }

    /**
     * Nama lengkap dengan jalur induknya, mis. "Belanja › ATK".
     */
    public function labelLengkap(): string
    {
        $induk = $this->induk?->namaTeks();

        return $induk ? $induk.' › '.$this->namaTeks() : $this->namaTeks();
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
