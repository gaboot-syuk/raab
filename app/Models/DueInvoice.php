<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan iuran untuk satu anggota pada satu periode.
 *
 * STATUS
 *  - `belum`      : tagihan terbit, belum ada pembayaran
 *  - `menunggu`   : ada pembayaran transfer, menunggu verifikasi Bendahara
 *  - `lunas`      : sudah dibayar (tunai langsung, atau transfer terverifikasi)
 *  - `dibebaskan` : ditandai lunas TANPA pembayaran — wajib beralasan
 *  - `ditolak`    : bukti pembayaran ditolak, anggota perlu unggah ulang
 *
 * `ditolak` sengaja mengembalikan tagihan ke keadaan "belum dibayar", bukan
 * menghapus pembayarannya — supaya riwayat unggahan tetap ada.
 */
#[Fillable([
    'due_category_id',
    'member_id',
    'periode_label',
    'nominal',
    'jatuh_tempo',
    'status',
    'dibebaskan_alasan',
    'dibebaskan_oleh',
    'dibuat_oleh',
    'catatan',
    'pengingat_terakhir_pada',
    'pengingat_terkirim',
])]
class DueInvoice extends Model
{
    public const STATUS_BELUM = 'belum';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_DIBEBASKAN = 'dibebaskan';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_BELUM => 'Belum Bayar',
        self::STATUS_MENUNGGU => 'Menunggu Verifikasi',
        self::STATUS_LUNAS => 'Lunas',
        self::STATUS_DIBEBASKAN => 'Dibebaskan',
        self::STATUS_DITOLAK => 'Perlu Unggah Ulang',
    ];

    /**
     * Status yang dianggap sudah beres — tidak perlu ditagih lagi.
     *
     * @var array<int, string>
     */
    public const STATUS_TUNTAS = [self::STATUS_LUNAS, self::STATUS_DIBEBASKAN];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'jatuh_tempo' => 'date',
            'pengingat_terakhir_pada' => 'datetime',
            'pengingat_terkirim' => 'integer',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(DueCategory::class, 'due_category_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(DuePayment::class, 'due_invoice_id')->orderByDesc('id');
    }

    public function scopeTuntas(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_TUNTAS);
    }

    public function scopeBelumTuntas(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::STATUS_TUNTAS);
    }

    public function scopePeriode(Builder $query, string $label): Builder
    {
        return $query->where('periode_label', $label);
    }

    public function tuntas(): bool
    {
        return in_array($this->status, self::STATUS_TUNTAS, true);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function terlambat(): bool
    {
        return ! $this->tuntas()
            && $this->jatuh_tempo !== null
            && $this->jatuh_tempo->isPast();
    }
}
