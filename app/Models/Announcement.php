<?php

namespace App\Models;

use App\Support\Audiens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Pengumuman.
 *
 * TIGA SAKELAR YANG HARUS DIBEDAKAN:
 *  - `tipe` publik|internal — boleh tidaknya muncul di halaman publik;
 *  - `target_audience`      — siapa yang boleh membacanya;
 *  - `publish_at`/`expire_at` — kapan ia berlaku.
 *
 * Pengumuman yang kedaluwarsa TIDAK dihapus, hanya berhenti tayang. Menghapus
 * berarti kehilangan jejak apa yang pernah diumumkan rayon dan kapan.
 */
#[Fillable([
    'slug',
    'judul',
    'isi',
    'tipe',
    'target_audience',
    'is_pinned',
    'publish_at',
    'expire_at',
    'dibuat_oleh',
])]
#[Hidden(['deleted_at'])]
class Announcement extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['judul', 'isi'];

    public const TIPE_PUBLIK = 'publik';

    public const TIPE_INTERNAL = 'internal';

    /**
     * @var array<string, string>
     */
    public const TIPE = [
        self::TIPE_PUBLIK => 'Publik',
        self::TIPE_INTERNAL => 'Internal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_audience' => 'array',
            'is_pinned' => 'boolean',
            'publish_at' => 'datetime',
            'expire_at' => 'datetime',
        ];
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /* ------------------------------------------------------------------ */
    /* Saringan                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Pengumuman yang SEDANG berlaku.
     *
     * Dua syarat, dan keduanya harus ditulis: yang belum waktunya tayang tidak
     * boleh muncul lebih awal, dan yang masa berlakunya sudah lewat tidak boleh
     * tetap menempel di halaman depan.
     */
    public function scopeTayang(Builder $query): Builder
    {
        return $query
            ->where('publish_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expire_at')->orWhere('expire_at', '>=', now()));
    }

    public function scopeTipe(Builder $query, string $tipe): Builder
    {
        return $query->where('tipe', $tipe);
    }

    public function scopeTerbaruDulu(Builder $query): Builder
    {
        // Disematkan selalu di atas; setelah itu yang paling baru.
        return $query->orderByDesc('is_pinned')->orderByDesc('publish_at');
    }

    /* ------------------------------------------------------------------ */
    /* Keadaan                                                             */
    /* ------------------------------------------------------------------ */

    public function sedangTayang(): bool
    {
        if ($this->publish_at !== null && $this->publish_at->isFuture()) {
            return false;
        }

        return $this->expire_at === null || $this->expire_at->isFuture();
    }

    public function terjadwal(): bool
    {
        return $this->publish_at !== null && $this->publish_at->isFuture();
    }

    public function kedaluwarsa(): bool
    {
        return $this->expire_at !== null && $this->expire_at->isPast();
    }

    public function labelWaktu(): string
    {
        return match (true) {
            $this->terjadwal() => 'Terjadwal',
            $this->kedaluwarsa() => 'Kedaluwarsa',
            default => 'Tayang',
        };
    }

    public function labelTipe(): string
    {
        return self::TIPE[$this->tipe] ?? $this->tipe;
    }

    public function judulTeks(): string
    {
        return $this->getTranslation('judul', 'id') ?: 'Pengumuman';
    }

    public function isiTeks(): string
    {
        return $this->getTranslation('isi', 'id') ?: '';
    }

    /**
     * Nama audiens yang boleh membaca, sudah diterjemahkan ke bahasa manusia.
     *
     * @return array<int, string>
     */
    public function audiensTeks(): array
    {
        return array_values(array_intersect_key(
            Audiens::PILIHAN,
            array_flip($this->target_audience ?? []),
        ));
    }

    public function bolehDibacaOleh(?User $pengguna): bool
    {
        return Audiens::boleh($this->target_audience, $pengguna);
    }

    /**
     * Slug unik dari judul, dengan nomor urut bila sudah terpakai.
     */
    public static function slugUnik(string $judul, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($judul) ?: 'pengumuman';
        $slug = $dasar;
        $n = 2;

        while (self::withTrashed()
            ->where('slug', $slug)
            ->when($kecualiId, fn (Builder $q) => $q->whereKeyNot($kecualiId))
            ->exists()) {
            $slug = $dasar.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
