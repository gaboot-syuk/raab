<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pembayaran iuran.
 *
 * Pembayaran TIDAK PERNAH DIHAPUS. Yang ditolak tetap tersimpan supaya jejak
 * "siapa mengunggah apa dan kapan" tidak hilang.
 *
 * Verifikasi pembayaranlah yang melahirkan transaksi kas masuk (sumber
 * `iuran`) — lihat App\Services\Iuran. Jadi uang tidak pernah dicatat dua kali:
 * satu kali di sini sebagai bukti, satu kali di buku kas sebagai saldo.
 */
#[Fillable([
    'due_invoice_id',
    'member_id',
    'jumlah',
    'metode',
    'bukti_media_id',
    'status',
    'catatan_pembayar',
    'catatan_bendahara',
    'dibayar_pada',
    'diverifikasi_oleh',
    'diverifikasi_pada',
    'transaction_id',
])]
class DuePayment extends Model
{
    public const METODE_TUNAI = 'tunai';

    public const METODE_TRANSFER = 'transfer';

    /**
     * @var array<string, string>
     */
    public const METODE = [
        self::METODE_TUNAI => 'Tunai',
        self::METODE_TRANSFER => 'Transfer',
    ];

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_TERVERIFIKASI = 'terverifikasi';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu Verifikasi',
        self::STATUS_TERVERIFIKASI => 'Terverifikasi',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'dibayar_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(DueInvoice::class, 'due_invoice_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, 'transaction_id');
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MENUNGGU);
    }

    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERVERIFIKASI);
    }

    public function labelMetode(): string
    {
        return self::METODE[$this->metode] ?? $this->metode;
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
