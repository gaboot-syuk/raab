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
 * Anggaran (RKAT) — rencana penerimaan atau pengeluaran.
 *
 * Realisasi TIDAK disimpan di kolom tersendiri: ia dihitung dari transaksi
 * terkonfirmasi yang ditautkan ke anggaran ini. Dengan begitu realisasi tidak
 * mungkin melenceng dari buku kas — dua angka yang harus sama tidak perlu
 * disimpan dua kali.
 */
#[Fillable([
    'period_id',
    'event_id',
    'category_id',
    'nama',
    'keterangan',
    'jenis',
    'jumlah_direncanakan',
    'aktif',
])]
class Budget extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_MASUK = 'masuk';

    public const JENIS_KELUAR = 'keluar';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_MASUK => 'Rencana Penerimaan',
        self::JENIS_KELUAR => 'Rencana Pengeluaran',
    ];

    public array $translatable = ['nama', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_direncanakan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'budget_id');
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

    /**
     * Jumlah terealisasi dari transaksi terkonfirmasi.
     */
    public function realisasi(): int
    {
        return (int) $this->transaksi()
            ->where('status', FinanceTransaction::STATUS_TERKONFIRMASI)
            ->sum('jumlah');
    }

    /**
     * Sisa anggaran. Negatif berarti melebihi rencana.
     */
    public function sisa(): int
    {
        return (int) $this->jumlah_direncanakan - $this->realisasi();
    }

    /**
     * Persentase realisasi (dibulatkan). Nol bila rencananya nol.
     */
    public function persenRealisasi(): int
    {
        if ($this->jumlah_direncanakan < 1) {
            return 0;
        }

        return (int) round($this->realisasi() / $this->jumlah_direncanakan * 100);
    }

    public function melebihiRencana(): bool
    {
        return $this->sisa() < 0;
    }
}
