<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan keanggotaan dari formulir /daftar.
 *
 * Isian formulir disimpan sebagai snapshot pada kolom `data`, sehingga riwayat
 * pengajuan tetap utuh walaupun anggota kemudian memperbarui profilnya.
 */
#[Fillable([
    'user_id',
    'member_id',
    'jalur',
    'status',
    'data',
    'catatan_pengurus',
    'catatan_internal',
    'diproses_oleh',
    'diproses_pada',
])]
class MemberApplication extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_PERBAIKAN = 'perbaikan';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu Verifikasi',
        self::STATUS_DISETUJUI => 'Disetujui',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_PERBAIKAN => 'Perlu Perbaikan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'diproses_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MENUNGGU);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Ambil satu nilai dari snapshot isian formulir.
     */
    public function isian(string $kunci, mixed $bawaan = null): mixed
    {
        return data_get($this->data, $kunci, $bawaan);
    }
}
