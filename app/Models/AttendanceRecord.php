<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kehadiran kader pada satu kegiatan — FAKTA, bukan niat.
 *
 * Indeks unik (activity_id, member_id) pada tabelnya menjamin satu orang hanya
 * punya satu catatan per kegiatan. Inilah yang membuat pemindaian QR tidak bisa
 * dipakai dua kali oleh orang yang sama: percobaan kedua ditolak basis data,
 * bukan sekadar diperiksa di kode yang bisa dilewati permintaan bersamaan.
 *
 * `terlambat` dan `hadir` sama-sama DIANGGAP HADIR untuk keperluan poin dan
 * rekap, tetapi tetap dibedakan supaya panitia tahu siapa yang datang lewat.
 */
#[Fillable([
    'activity_id',
    'member_id',
    'status',
    'metode',
    'catatan',
    'dicatat_oleh',
    'dicatat_pada',
])]
class AttendanceRecord extends Model
{
    public const STATUS_HADIR = 'hadir';

    public const STATUS_TERLAMBAT = 'terlambat';

    public const STATUS_IZIN = 'izin';

    public const STATUS_SAKIT = 'sakit';

    public const STATUS_ALPA = 'alpa';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_HADIR => 'Hadir',
        self::STATUS_TERLAMBAT => 'Terlambat',
        self::STATUS_IZIN => 'Izin',
        self::STATUS_SAKIT => 'Sakit',
        self::STATUS_ALPA => 'Tanpa Keterangan',
    ];

    /**
     * Status yang dihitung sebagai KEHADIRAN.
     *
     * Izin dan sakit tetap dicatat dan tetap dihargai secara sosial, tetapi
     * bukan kehadiran — kalau dihitung hadir, "aktif" kehilangan artinya.
     *
     * @var array<int, string>
     */
    public const HADIR = [self::STATUS_HADIR, self::STATUS_TERLAMBAT];

    public const METODE_MANUAL = 'manual';

    public const METODE_QR = 'qr';

    /**
     * @var array<string, string>
     */
    public const METODE = [
        self::METODE_MANUAL => 'Dicatat pengurus',
        self::METODE_QR => 'Pindai QR',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dicatat_pada' => 'datetime',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(AttendanceActivity::class, 'activity_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function scopeHadir(Builder $query): Builder
    {
        return $query->whereIn('status', self::HADIR);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function labelMetode(): string
    {
        return self::METODE[$this->metode] ?? $this->metode;
    }

    public function dihitungHadir(): bool
    {
        return in_array($this->status, self::HADIR, true);
    }
}
