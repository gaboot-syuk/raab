<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan seorang pengurus pada jabatan tertentu dalam suatu periode.
 *
 * `member_id` boleh kosong bila pengurus ditunjuk dengan nama manual
 * (mis. dosen pembina yang bukan anggota terdaftar).
 */
#[Fillable([
    'period_id',
    'position_id',
    'member_id',
    'nama_manual',
    'keterangan',
    'urutan',
    'aktif',
])]
class PositionAssignment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Nama pengurus: dari data anggota bila tertaut, jika tidak dari nama manual.
     */
    public function namaTampil(): string
    {
        return $this->member?->nama_lengkap ?? (string) $this->nama_manual;
    }
}
