<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Kategori iuran (mis. Iuran Anggota Aktif, Iuran Pengurus, Iuran Alumni).
 *
 * `target_audiens` menentukan siapa yang otomatis menerima tagihan saat
 * Bendahara menerbitkan periode baru. Kategori boleh DINONAKTIFKAN tanpa
 * menghapus riwayat tagihan — mematikan kategori lama bukan alasan untuk
 * menghapus catatan uang yang sudah masuk.
 */
#[Fillable([
    'kode',
    'nama',
    'keterangan',
    'target_audiens',
    'nominal',
    'periode',
    'urutan',
    'aktif',
])]
class DueCategory extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const TARGET_KADER_AKTIF = 'kader_aktif';

    public const TARGET_PENGURUS = 'pengurus';

    public const TARGET_ALUMNI = 'alumni';

    public const TARGET_SEMUA = 'semua';

    /**
     * @var array<string, string>
     */
    public const TARGET = [
        self::TARGET_KADER_AKTIF => 'Kader Aktif',
        self::TARGET_PENGURUS => 'Pengurus',
        self::TARGET_ALUMNI => 'Alumni',
        self::TARGET_SEMUA => 'Semua Anggota',
    ];

    public const PERIODE_BULANAN = 'bulanan';

    public const PERIODE_TAHUNAN = 'tahunan';

    public const PERIODE_SEKALI = 'sekali';

    /**
     * @var array<string, string>
     */
    public const PERIODE = [
        self::PERIODE_BULANAN => 'Bulanan',
        self::PERIODE_TAHUNAN => 'Tahunan',
        self::PERIODE_SEKALI => 'Sekali Bayar',
    ];

    public array $translatable = ['nama', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(DueInvoice::class, 'due_category_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('kode');
    }

    public function namaTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('nama', $bahasa, false)
            ?: $this->getTranslation('nama', 'id', false)
            ?: $this->kode);
    }

    public function labelTarget(): string
    {
        return self::TARGET[$this->target_audiens] ?? $this->target_audiens;
    }

    public function labelPeriode(): string
    {
        return self::PERIODE[$this->periode] ?? $this->periode;
    }
}
