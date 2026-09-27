<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Event kaderisasi: Mapaba, PKD, dan kegiatan lain yang memakai pendaftaran.
 *
 * PINTU MASUK PENDAFTARAN ADA DI `menerimaPendaftaran()`.
 * Halaman publik, formulir, dan titik akhir kiriman semuanya memanggil metode
 * itu — jadi aturan "kuota penuh" dan "sudah lewat tanggal" tidak mungkin
 * berbeda antara tampilan dan kenyataan.
 */
#[Fillable([
    'jenis',
    'judul',
    'slug',
    'deskripsi',
    'syarat',
    'poster_media_id',
    'kuota',
    'pendaftaran_dibuka',
    'pendaftaran_ditutup',
    'mulai',
    'selesai',
    'lokasi',
    'biaya',
    'aktif',
    'urutan',
])]
class Event extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public const JENIS_MAPABA = 'mapaba';

    public const JENIS_PKD = 'pkd';

    public const JENIS_LAIN = 'lain';

    /**
     * @var array<string, string>
     */
    public const JENIS = [
        self::JENIS_MAPABA => 'Mapaba',
        self::JENIS_PKD => 'PKD',
        self::JENIS_LAIN => 'Kegiatan Lain',
    ];

    public array $translatable = ['judul', 'slug', 'deskripsi', 'syarat'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'syarat' => 'array',
            'pendaftaran_dibuka' => 'datetime',
            'pendaftaran_ditutup' => 'datetime',
            'mulai' => 'date',
            'selesai' => 'date',
            'kuota' => 'integer',
            'biaya' => 'integer',
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function pendaftaran(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function kolom(): HasMany
    {
        return $this->hasMany(EventField::class)->orderBy('urutan');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeJenis(Builder $query, string $jenis): Builder
    {
        return $query->where('jenis', $jenis);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderByDesc('mulai')->orderByDesc('id');
    }

    /**
     * Event yang belum berjalan — untuk bagian "akan datang".
     */
    public function scopeMendatang(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('mulai')->orWhere('mulai', '>=', now()->toDateString()))
            ->orderBy('mulai');
    }

    public function scopeLampau(Builder $query): Builder
    {
        return $query->whereNotNull('mulai')
            ->where('mulai', '<', now()->toDateString())
            ->orderByDesc('mulai');
    }

    /**
     * Judul event untuk ditampilkan, dengan bahasa cadangan.
     *
     * Sejajar dengan `judulTeks()`/`namaTeks()` pada model lain, supaya daftar
     * pilihan di panel (anggaran, hibah, transaksi) tidak perlu tahu soal
     * struktur terjemahan.
     */
    public function judulTeks(string $bahasa = 'id'): string
    {
        return (string) ($this->getTranslation('judul', $bahasa, false)
            ?: $this->getTranslation('judul', 'id', false)
            ?: 'Event #'.$this->id);
    }

    /**
     * Cari berdasarkan slug pada bahasa yang diminta.
     */
    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug->id', $slug)->orWhere('slug->en', $slug);
    }

    /* ------------------------------------------------------------------ */
    /* Aturan pendaftaran                                                  */
    /* ------------------------------------------------------------------ */

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? Str::headline($this->jenis);
    }

    /**
     * Jumlah pendaftar yang masih dihitung memakai kuota.
     *
     * Pendaftar yang DIBATALKAN tidak dihitung — kursinya kembali tersedia.
     */
    public function terisi(): int
    {
        return $this->pendaftaran()
            ->where('status', '!=', EventRegistration::STATUS_BATAL)
            ->count();
    }

    /**
     * Sisa kursi. `null` berarti tidak dibatasi kuota.
     */
    public function sisaKuota(): ?int
    {
        if ($this->kuota === null) {
            return null;
        }

        return max(0, $this->kuota - $this->terisi());
    }

    /**
     * Apakah pendaftaran sedang dibuka?
     */
    public function menerimaPendaftaran(): bool
    {
        return $this->alasanTutup() === null;
    }

    /**
     * Alasan pendaftaran tertutup — untuk ditampilkan apa adanya kepada
     * pengunjung, bukan sekadar tombol mati tanpa penjelasan.
     */
    public function alasanTutup(): ?string
    {
        if (! $this->aktif) {
            return 'Event ini sedang tidak dibuka untuk pendaftaran.';
        }

        if ($this->pendaftaran_dibuka && $this->pendaftaran_dibuka->isFuture()) {
            return 'Pendaftaran dibuka '.$this->pendaftaran_dibuka->translatedFormat('d F Y, H:i').' WIB.';
        }

        if ($this->pendaftaran_ditutup && $this->pendaftaran_ditutup->isPast()) {
            return 'Pendaftaran sudah ditutup pada '.$this->pendaftaran_ditutup->translatedFormat('d F Y, H:i').' WIB.';
        }

        if ($this->kuota !== null && $this->sisaKuota() === 0) {
            return 'Kuota sudah penuh ('.$this->kuota.' pendaftar).';
        }

        return null;
    }

    /**
     * Keadaan pendaftaran untuk ditampilkan di antarmuka.
     *
     * @return array<string, mixed>
     */
    public function keadaanPendaftaran(): array
    {
        return [
            'dibuka' => $this->menerimaPendaftaran(),
            'alasan' => $this->alasanTutup(),
            'terisi' => $this->terisi(),
            'kuota' => $this->kuota,
            'sisa' => $this->sisaKuota(),
            'dibuka_pada' => $this->pendaftaran_dibuka?->toIso8601String(),
            'ditutup_pada' => $this->pendaftaran_ditutup?->toIso8601String(),
        ];
    }

    /**
     * Slug unik dari judul Indonesia.
     */
    public static function slugUnik(string $judul, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($judul) ?: 'event';

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
