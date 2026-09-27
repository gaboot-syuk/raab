<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permohonan perpanjangan masa pinjam.
 *
 * Perpanjangan TIDAK langsung mengubah jatuh tempo; ia harus disetujui lebih
 * dulu. Jatuh tempo lama dan barunya disimpan keduanya agar riwayatnya jelas.
 */
#[Fillable([
    'loan_id',
    'alasan',
    'status',
    'jatuh_tempo_lama',
    'jatuh_tempo_baru',
    'diproses_oleh',
    'diproses_pada',
    'catatan_petugas',
])]
class LoanExtension extends Model
{
    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DIAJUKAN => 'Menunggu Persetujuan',
        self::STATUS_DISETUJUI => 'Disetujui',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jatuh_tempo_lama' => 'datetime',
            'jatuh_tempo_baru' => 'datetime',
            'diproses_pada' => 'datetime',
        ];
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }
}
