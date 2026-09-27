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
 * Satu jenis aset rayon.
 *
 * `jumlah` adalah angka TERKINI dan hanya boleh berubah lewat
 * `InventoryMovement` (lihat App\Services\Inventaris). Jangan pernah menulisnya
 * langsung dari controller — riwayatnya akan hilang.
 */
#[Fillable([
    'category_id',
    'kode',
    'nama',
    'keterangan',
    'satuan',
    'jumlah',
    'jumlah_minimum',
    'kondisi',
    'lokasi',
    'nilai',
    'foto_media_id',
    'is_public',
    'aktif',
])]
class InventoryItem extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const KONDISI_BAIK = 'baik';

    public const KONDISI_RUSAK_RINGAN = 'rusak_ringan';

    public const KONDISI_RUSAK_BERAT = 'rusak_berat';

    /**
     * @var array<string, string>
     */
    public const KONDISI = [
        self::KONDISI_BAIK => 'Baik',
        self::KONDISI_RUSAK_RINGAN => 'Rusak Ringan',
        self::KONDISI_RUSAK_BERAT => 'Rusak Berat',
    ];

    public array $translatable = ['nama', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'jumlah_minimum' => 'integer',
            'nilai' => 'integer',
            'is_public' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id')->orderByDesc('terjadi_pada')->orderByDesc('id');
    }

    public function peminjaman(): HasMany
    {
        return $this->hasMany(Loan::class, 'inventory_item_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Hanya barang yang ditandai boleh dibagikan yang tampil di /inventaris.
     */
    public function scopePublik(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('aktif', true);
    }

    public function namaTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('nama', $bahasa, false)
            ?: $this->getTranslation('nama', 'id', false)
            ?: $this->kode);
    }

    public function labelKondisi(): string
    {
        return self::KONDISI[$this->kondisi] ?? ucfirst((string) $this->kondisi);
    }

    /**
     * Apakah stok sudah menyentuh batas minimum?
     */
    public function stokMenipis(): bool
    {
        return $this->jumlah_minimum > 0 && $this->jumlah <= $this->jumlah_minimum;
    }

    /**
     * Jumlah yang sedang dipinjam tetapi belum kembali.
     */
    public function jumlahDipinjam(): int
    {
        return (int) $this->peminjaman()
            ->whereIn('status', Loan::STATUS_BERJALAN)
            ->sum('jumlah');
    }

    /**
     * Jumlah unit yang benar-benar bebas dipakai (tidak sedang dipinjam).
     */
    public function jumlahTersedia(): int
    {
        return max(0, $this->jumlah - $this->jumlahDipinjam());
    }
}
