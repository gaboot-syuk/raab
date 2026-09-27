<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Satu akun kas rayon (mis. Kas Utama, Kas Kegiatan).
 *
 * `saldo_berjalan` adalah angka TERKINI dan hanya boleh berubah lewat
 * `FinanceTransaction` yang dikonfirmasi — lihat App\Services\Kas. Jangan
 * pernah menulisnya langsung dari controller: jejak auditnya akan hilang.
 */
#[Fillable([
    'kode',
    'nama',
    'keterangan',
    'jenis',
    'saldo_awal',
    'saldo_berjalan',
    'urutan',
    'aktif',
])]
class FinanceAccount extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_UTAMA = 'utama';

    public const JENIS_KEGIATAN = 'kegiatan';

    public const JENIS_LAIN = 'lain';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_UTAMA => 'Kas Utama',
        self::JENIS_KEGIATAN => 'Kas Kegiatan',
        self::JENIS_LAIN => 'Kas Lain',
    ];

    public array $translatable = ['nama', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'saldo_awal' => 'integer',
            'saldo_berjalan' => 'integer',
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'account_id')->orderByDesc('tanggal')->orderByDesc('id');
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

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
