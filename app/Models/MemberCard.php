<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Kartu kader digital.
 *
 * Token pada kartu dipakai untuk halaman verifikasi publik
 * /verifikasi-kader/{token} — lihat Fase 8. Kartu dicabut otomatis ketika
 * status anggota berubah menjadi alumni atau nonaktif.
 */
#[Fillable([
    'member_id',
    'nomor_kartu',
    'token',
    'status',
    'berlaku_sampai',
    'diterbitkan_pada',
    'dicabut_pada',
    'alasan_pencabutan',
])]
class MemberCard extends Model
{
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_DICABUT = 'dicabut';

    /**
     * Sebab sebuah kartu tidak lagi sah.
     *
     * Dipisah dari status kartu karena "tidak aktif" punya dua sebab berbeda
     * dan halaman verifikasi publik harus bisa menyebutkannya: pencabutan oleh
     * sekretariat, atau masa berlaku yang habis. Tanpa pemisahan ini, kader
     * yang kartunya hanya kedaluwarsa akan diberi tahu bahwa kartunya
     * "dicabut" — padahal ia tidak melakukan kesalahan apa pun.
     */
    public const ALAS_DICABUT = 'dicabut';

    public const ALAS_KEDALUWARSA = 'kedaluwarsa';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'berlaku_sampai' => 'date',
            'diterbitkan_pada' => 'datetime',
            'dicabut_pada' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    /**
     * Token acak untuk tautan verifikasi publik.
     *
     * Memakai 48 karakter acak sehingga tidak dapat ditebak dari nomor anggota.
     */
    public static function buatToken(): string
    {
        return Str::random(48);
    }

    /**
     * Sebab kartu ini tidak sah, atau null bila masih sah.
     *
     * SATU-SATUNYA tempat aturan keabsahan ditulis. `masihSah()`, halaman
     * verifikasi publik, dan badge di dasbor semuanya membaca metode ini, agar
     * tidak ada dua jawaban berbeda untuk pertanyaan yang sama.
     */
    public function alasanTidakSah(): ?string
    {
        if ($this->status !== self::STATUS_AKTIF) {
            return self::ALAS_DICABUT;
        }

        if ($this->berlaku_sampai !== null && $this->berlaku_sampai->isPast()) {
            return self::ALAS_KEDALUWARSA;
        }

        return null;
    }

    /**
     * Kartu masih sah dipakai?
     */
    public function masihSah(): bool
    {
        return $this->alasanTidakSah() === null;
    }
}
