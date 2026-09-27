<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu salinan fisik buku.
 *
 * Status eksemplar inilah yang menentukan ketersediaan katalog — jumlah
 * eksemplar berstatus `tersedia` dibandingkan dengan totalnya.
 */
#[Fillable([
    'book_id',
    'kode_eksemplar',
    'kondisi',
    'status',
    'rak',
    'tanggal_perolehan',
    'nilai',
    'catatan',
])]
class BookCopy extends Model
{
    public const STATUS_TERSEDIA = 'tersedia';

    public const STATUS_DIPINJAM = 'dipinjam';

    public const STATUS_PERBAIKAN = 'perbaikan';

    public const STATUS_HILANG = 'hilang';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_TERSEDIA => 'Tersedia',
        self::STATUS_DIPINJAM => 'Sedang Dipinjam',
        self::STATUS_PERBAIKAN => 'Dalam Perbaikan',
        self::STATUS_HILANG => 'Hilang',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'nilai' => 'integer',
        ];
    }

    public function buku(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function peminjaman(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Loan::class, 'book_copy_id');
    }

    public function scopeTersedia(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERSEDIA);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Hanya eksemplar yang benar-benar ada di rak yang boleh dipinjam.
     */
    public function bisaDipinjam(): bool
    {
        return $this->status === self::STATUS_TERSEDIA;
    }

    /**
     * Peminjaman aktif atas eksemplar ini (kalau ada).
     */
    public function pinjamanAktif(): ?Loan
    {
        return $this->peminjaman()
            ->whereIn('status', Loan::STATUS_BERJALAN)
            ->latest('id')
            ->first();
    }
}
