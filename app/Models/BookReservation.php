<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrian reservasi buku.
 *
 * Muncul otomatis saat semua eksemplar judul tertentu sedang dipinjam. Begitu
 * satu eksemplar kembali, yang paling depan berstatus `siap` dengan masa
 * berlaku terbatas; lewat dari itu antriannya dilewati dan yang berikutnya
 * naik — lihat App\Services\Peminjaman.
 */
#[Fillable([
    'book_id',
    'member_id',
    'status',
    'posisi',
    'siap_pada',
    'kedaluwarsa_pada',
    'diambil_pada',
    'ingat_siap_pada',
    'catatan',
])]
class BookReservation extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_SIAP = 'siap';

    public const STATUS_DIAMBIL = 'diambil';

    public const STATUS_KEDALUWARSA = 'kedaluwarsa';

    public const STATUS_BATAL = 'batal';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_MENUNGGU => 'Menunggu Giliran',
        self::STATUS_SIAP => 'Siap Diambil',
        self::STATUS_DIAMBIL => 'Sudah Diambil',
        self::STATUS_KEDALUWARSA => 'Kedaluwarsa',
        self::STATUS_BATAL => 'Dibatalkan',
    ];

    /**
     * Status yang masih menahan kursi pada antrian.
     *
     * @var array<int, string>
     */
    public const STATUS_AKTIF = [self::STATUS_MENUNGGU, self::STATUS_SIAP];

    /**
     * Masa berlaku setelah buku dinyatakan siap diambil.
     */
    public const BERLAKU_JAM = 48;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posisi' => 'integer',
            'siap_pada' => 'datetime',
            'kedaluwarsa_pada' => 'datetime',
            'diambil_pada' => 'datetime',
            'ingat_siap_pada' => 'date',
        ];
    }

    public function buku(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_AKTIF);
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MENUNGGU)->orderBy('posisi');
    }

    /**
     * Reservasi yang masa berlakunya sudah lewat — kandidat untuk dilewati.
     */
    public function scopeKedaluwarsa(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SIAP)
            ->whereNotNull('kedaluwarsa_pada')
            ->where('kedaluwarsa_pada', '<', now());
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Sisa waktu untuk mengambil buku, dalam jam.
     */
    public function sisaJam(): ?int
    {
        if ($this->kedaluwarsa_pada === null) {
            return null;
        }

        return $this->kedaluwarsa_pada->isFuture()
            ? (int) now()->diffInHours($this->kedaluwarsa_pada)
            : 0;
    }
}
