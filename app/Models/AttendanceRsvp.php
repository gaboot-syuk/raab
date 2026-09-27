<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Janji hadir seorang kader sebelum kegiatan berlangsung.
 *
 * BEDA dari `AttendanceRecord`: RSVP itu NIAT, presensi itu FAKTA. Keduanya
 * dipisah karena rekap kehadiran tidak boleh ikut menghitung orang yang
 * berjanji datang lalu tidak muncul.
 *
 * Satu anggota hanya punya satu jawaban per kegiatan. Mengubah jawaban berarti
 * memperbarui baris yang sama, bukan menumpuk baris baru.
 */
#[Fillable([
    'activity_id',
    'member_id',
    'status',
    'catatan',
])]
class AttendanceRsvp extends Model
{
    public const STATUS_HADIR = 'hadir';

    public const STATUS_TIDAK_HADIR = 'tidak_hadir';

    public const STATUS_MUNGKIN = 'mungkin';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_HADIR => 'Akan Hadir',
        self::STATUS_TIDAK_HADIR => 'Tidak Hadir',
        self::STATUS_MUNGKIN => 'Belum Pasti',
    ];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(AttendanceActivity::class, 'activity_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }
}
