<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu catatan perubahan stok aset.
 *
 * Inilah SATU-SATUNYA jalan mengubah `InventoryItem::$jumlah`. Angka sebelum
 * dan sesudah disimpan supaya riwayatnya tetap terbaca walau asetnya sudah
 * berubah berkali-kali.
 */
#[Fillable([
    'item_id',
    'jenis',
    'jumlah',
    'jumlah_sebelum',
    'jumlah_sesudah',
    'penanggung_jawab_id',
    'penanggung_jawab_nama',
    'event_id',
    'dicatat_oleh',
    'catatan',
    'terjadi_pada',
])]
class InventoryMovement extends Model
{
    public const JENIS_MASUK = 'masuk';

    public const JENIS_KELUAR = 'keluar';

    public const JENIS_PENYESUAIAN = 'penyesuaian';

    public const JENIS_RUSAK = 'rusak';

    public const JENIS_HILANG = 'hilang';

    public const JENIS_PERBAIKAN = 'perbaikan';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_MASUK => 'Barang Masuk',
        self::JENIS_KELUAR => 'Barang Keluar',
        self::JENIS_PENYESUAIAN => 'Penyesuaian Stok',
        self::JENIS_RUSAK => 'Rusak',
        self::JENIS_HILANG => 'Hilang',
        self::JENIS_PERBAIKAN => 'Selesai Diperbaiki',
    ];

    /**
     * Jenis mutasi yang WAJIB menyebutkan penanggung jawab.
     *
     * Barang rusak dan hilang adalah kehilangan aset organisasi — harus jelas
     * siapa yang bertanggung jawab, bukan sekadar berkurang begitu saja.
     *
     * @var array<int, string>
     */
    public const WAJIB_PENANGGUNG_JAWAB = [self::JENIS_RUSAK, self::JENIS_HILANG];

    /**
     * Arah pengaruh terhadap stok.
     *
     * @var array<string, int>
     */
    public const ARAH = [
        self::JENIS_MASUK => 1,
        self::JENIS_PERBAIKAN => 1,
        self::JENIS_KELUAR => -1,
        self::JENIS_RUSAK => -1,
        self::JENIS_HILANG => -1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'jumlah_sebelum' => 'integer',
            'jumlah_sesudah' => 'integer',
            'terjadi_pada' => 'datetime',
        ];
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'penanggung_jawab_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeUntukItem(Builder $query, int $itemId): Builder
    {
        return $query->where('item_id', $itemId);
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    public function mengubahStok(): bool
    {
        return array_key_exists($this->jenis, self::ARAH);
    }

    public function namaPenanggungJawab(): ?string
    {
        return $this->penanggung_jawab_nama ?: $this->penanggungJawab?->nama_lengkap;
    }
}
