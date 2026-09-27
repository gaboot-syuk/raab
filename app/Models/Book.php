<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Judul buku pada katalog.
 *
 * Peminjaman TIDAK menyentuh tabel ini — yang dipinjam adalah eksemplar
 * (`book_copies`). Ketersediaan karena itu dihitung dari eksemplar.
 */
#[Fillable([
    'judul',
    'slug',
    'sinopsis',
    'penulis',
    'penerbit',
    'tahun_terbit',
    'isbn',
    'ddc',
    'kategori',
    'bahasa',
    'jumlah_halaman',
    'cover_media_id',
    'rak',
    'is_public',
    'aktif',
])]
class Book extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['judul', 'slug', 'sinopsis'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun_terbit' => 'integer',
            'jumlah_halaman' => 'integer',
            'is_public' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function eksemplar(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function reservasi(): HasMany
    {
        return $this->hasMany(BookReservation::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Hanya judul yang boleh tampil di katalog publik.
     */
    public function scopePublik(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('aktif', true);
    }

    public function scopeKategori(Builder $query, string $kategori): Builder
    {
        return $query->where('kategori', $kategori);
    }

    /**
     * Pencarian judul, penulis, atau ISBN.
     */
    public function scopeCari(Builder $query, string $kata): Builder
    {
        $kata = trim($kata);

        return $query->where(function (Builder $q) use ($kata): void {
            $q->where('judul->id', 'like', "%{$kata}%")
                ->orWhere('judul->en', 'like', "%{$kata}%")
                ->orWhere('penulis', 'like', "%{$kata}%")
                ->orWhere('isbn', 'like', "%{$kata}%")
                ->orWhere('penerbit', 'like', "%{$kata}%");
        });
    }

    public function judulTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('judul', $bahasa, false)
            ?: $this->getTranslation('judul', 'id', false)
            ?: 'Tanpa Judul');
    }

    /**
     * Jumlah eksemplar yang siap dipinjam.
     */
    public function eksemplarTersedia(): int
    {
        return $this->eksemplar()->where('status', BookCopy::STATUS_TERSEDIA)->count();
    }

    public function totalEksemplar(): int
    {
        return $this->eksemplar()->count();
    }

    /**
     * Eksemplar yang benar-benar boleh diambil siapa pun.
     *
     * Eksemplar yang sedang ditahan untuk orang di daftar tunggu (reservasi
     * berstatus SIAP) dikurangi, supaya katalog tidak menjanjikan sesuatu yang
     * pasti ditolak saat diajukan.
     */
    public function eksemplarBebas(): int
    {
        $ditahan = BookReservation::query()
            ->where('book_id', $this->id)
            ->where('status', BookReservation::STATUS_SIAP)
            ->count();

        return max(0, $this->eksemplarTersedia() - $ditahan);
    }

    public function tersediaBebas(): bool
    {
        return $this->eksemplarBebas() > 0;
    }

    /**
     * Judul yang punya eksemplar benar-benar bebas (eksemplar yang ditahan
     * untuk antrian tidak dihitung).
     *
     * Dipakai katalog publik supaya angka di kepala halaman, filter "hanya yang
     * tersedia", dan label pada kartu selalu sepakat.
     */
    public function scopePunyaEksemplarBebas(Builder $query): Builder
    {
        return $query->whereHas('eksemplar', function ($q): void {
            $q->where('status', BookCopy::STATUS_TERSEDIA)
                ->whereRaw(
                    '(select count(*) from book_reservations'
                    .' where book_reservations.book_id = books.id'
                    ." and book_reservations.status = ?) < "
                    .'(select count(*) from book_copies'
                    .' where book_copies.book_id = books.id'
                    .' and book_copies.status = ?)',
                    [BookReservation::STATUS_SIAP, BookCopy::STATUS_TERSEDIA],
                );
        });
    }

    /**
     * Reservasi berstatus "siap diambil" milik anggota tertentu.
     *
     * Dipakai untuk membedakan "semua eksemplar dipinjam" dari "ada eksemplar
     * yang sedang ditahan untukmu" — dua hal yang berbeda bagi anggota.
     */
    public function reservasiSiapUntuk(?Member $anggota): ?BookReservation
    {
        if ($anggota === null) {
            return null;
        }

        return BookReservation::query()
            ->where('book_id', $this->id)
            ->where('member_id', $anggota->id)
            ->where('status', BookReservation::STATUS_SIAP)
            ->first();
    }

    public function tersedia(): bool
    {
        return $this->eksemplarTersedia() > 0;
    }

    /**
     * Posisi anggota pada antrian judul ini, atau null bila tidak mengantre.
     */
    public function posisiAntrian(Member $anggota): ?int
    {
        return BookReservation::query()
            ->where('book_id', $this->id)
            ->where('member_id', $anggota->id)
            ->whereIn('status', BookReservation::STATUS_AKTIF)
            ->value('posisi');
    }

    public static function slugUnik(string $judul, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($judul) ?: 'buku';

        $slug = $dasar;
        $urutan = 2;

        while (self::query()
            ->where('slug->id', $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$urutan;
            $urutan++;
        }

        return $slug;
    }
}
