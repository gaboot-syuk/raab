<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris peristiwa dalam buku besar poin kontribusi.
 *
 * YANG DISIMPAN ADALAH PERISTIWANYA, bukan totalnya. Total dijumlahkan saat
 * dibutuhkan, sehingga setiap angka bisa dipertanggungjawabkan asalnya.
 *
 * `sidik` bersifat unik di basis data. Itulah yang membuat poin tidak mungkin
 * tercatat dua kali untuk satu peristiwa, walau pemberiannya dijalankan
 * berkali-kali.
 */
#[Fillable([
    'member_id',
    'sumber',
    'sidik',
    'poin',
    'periode_label',
    'keterangan',
    'terjadi_pada',
    'activity_id',
    'diberikan_oleh',
])]
class ContributionPoint extends Model
{
    public const SUMBER_PRESENSI = 'presensi';

    public const SUMBER_ARTIKEL = 'artikel';

    public const SUMBER_PRESTASI = 'prestasi';

    public const SUMBER_MANUAL = 'manual';

    /**
     * @var array<string, string>
     */
    public const SUMBER = [
        self::SUMBER_PRESENSI => 'Kehadiran Kegiatan',
        self::SUMBER_ARTIKEL => 'Karya Terbit',
        self::SUMBER_PRESTASI => 'Prestasi Kader',
        self::SUMBER_MANUAL => 'Penyesuaian Pengurus',
    ];

    /**
     * Batas penyesuaian manual dalam satu kali pemberian.
     *
     * Bukan batas teknis, melainkan pengaman salah ketik: menambahkan "100"
     * alih-alih "10" akan membuat satu orang melompati seluruh papan peringkat.
     */
    public const BATAS_PENYESUAIAN = 50;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'poin' => 'integer',
            'terjadi_pada' => 'datetime',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(AttendanceActivity::class, 'activity_id');
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diberikan_oleh');
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function scopeSah(Builder $query): Builder
    {
        return $query->whereNull('dibatalkan_pada');
    }

    public function scopePeriode(Builder $query, string $label): Builder
    {
        return $query->where('periode_label', $label);
    }

    public function scopeSumber(Builder $query, string $sumber): Builder
    {
        return $query->where('sumber', $sumber);
    }

    /**
     * Sidik peristiwa — satu-satunya penjaga agar poin tidak berganda.
     */
    public static function sidikPresensi(int $activityId, int $memberId): string
    {
        return 'presensi:kegiatan:'.$activityId.':anggota:'.$memberId;
    }

    public static function sidikArtikel(int $articleId, int $memberId): string
    {
        return 'artikel:'.$articleId.':anggota:'.$memberId;
    }

    public function dibatalkan(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    public function labelSumber(): string
    {
        return self::SUMBER[$this->sumber] ?? $this->sumber;
    }

    /**
     * Poin yang benar-benar dihitung: baris yang sudah dibatalkan bernilai nol.
     */
    public function poinSah(): int
    {
        return $this->dibatalkan() ? 0 : $this->poin;
    }

    public static function periodeDari(\DateTimeInterface|string $tanggal): string
    {
        return \Illuminate\Support\Carbon::parse($tanggal)->format('Y-m');
    }
}
