<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak perubahan status keanggotaan — tidak ada perubahan tanpa catatan.
 */
#[Fillable([
    'member_id',
    'status_lama',
    'status_baru',
    'jalur_lama',
    'jalur_baru',
    'alasan',
    'diubah_oleh',
])]
class MemberStatusHistory extends Model
{
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
