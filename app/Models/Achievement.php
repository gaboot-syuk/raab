<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Prestasi kader.
 *
 * STATUS
 *  - `diajukan`      : diklaim kader, BELUM diperiksa siapa pun
 *  - `terverifikasi` : sertifikatnya sudah diperiksa pengurus — inilah satu-satunya
 *                      keadaan yang boleh tayang publik dan menghasilkan poin
 *  - `ditolak`       : klaim tidak dapat diterima, wajib beralasan
 *
 * KLAIM BELUM TENTANG DIANGGAP BENAR. Itulah alasan status ini ada: tanpa
 * langkah verifikasi, siapa pun bisa menuliskan dirinya juara nasional dan
 * langsung mendapat poin serta tayang di halaman publik rayon.
 */
#[Fillable([
    'member_id',
    'achievement_category_id',
    'judul',
    'deskripsi',
    'penyelenggara',
    'tingkat',
    'peringkat',
    'tanggal',
    'sertifikat_media_id',
    'tautan_bukti',
    'status',
    'unggulan',
    'tampil_publik',
    'diajukan_oleh',
])]
class Achievement extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['judul', 'deskripsi'];

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_TERVERIFIKASI = 'terverifikasi';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DIAJUKAN => 'Menunggu Diperiksa',
        self::STATUS_TERVERIFIKASI => 'Terverifikasi',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    /* ------------------------------------------------------------------ */
    /* Tingkat                                                             */
    /* ------------------------------------------------------------------ */

    public const TINGKAT_RAYON = 'rayon';

    public const TINGKAT_KAMPUS = 'kampus';

    public const TINGKAT_REGIONAL = 'regional';

    public const TINGKAT_NASIONAL = 'nasional';

    public const TINGKAT_INTERNASIONAL = 'internasional';

    /**
     * Urutan dari yang paling sempit ke paling luas — dipakai untuk menyaring
     * dan mengurutkan, bukan untuk menilai siapa lebih hebat.
     *
     * @var array<string, string>
     */
    public const TINGKAT = [
        self::TINGKAT_RAYON => 'Tingkat Rayon',
        self::TINGKAT_KAMPUS => 'Tingkat Kampus',
        self::TINGKAT_REGIONAL => 'Tingkat Regional',
        self::TINGKAT_NASIONAL => 'Tingkat Nasional',
        self::TINGKAT_INTERNASIONAL => 'Tingkat Internasional',
    ];

    /**
     * Poin kontribusi per tingkat.
     *
     * DISIMPAN DI KODE, bukan di pengaturan situs: besaran poin mengubah papan
     * peringkat, jadi ia harus ikut terversi dan tidak bisa diubah diam-diam
     * dari panel.
     */
    public const POIN_TINGKAT = [
        self::TINGKAT_RAYON => 2,
        self::TINGKAT_KAMPUS => 4,
        self::TINGKAT_REGIONAL => 6,
        self::TINGKAT_NASIONAL => 8,
        self::TINGKAT_INTERNASIONAL => 12,
    ];

    /* ------------------------------------------------------------------ */
    /* Peringkat                                                           */
    /* ------------------------------------------------------------------ */

    public const PERINGKAT_JUARA_1 = 'juara_1';

    public const PERINGKAT_JUARA_2 = 'juara_2';

    public const PERINGKAT_JUARA_3 = 'juara_3';

    public const PERINGKAT_HARAPAN = 'harapan';

    public const PERINGKAT_FINALIS = 'finalis';

    public const PERINGKAT_PESERTA = 'peserta';

    /**
     * @var array<string, string>
     */
    public const PERINGKAT = [
        self::PERINGKAT_JUARA_1 => 'Juara 1',
        self::PERINGKAT_JUARA_2 => 'Juara 2',
        self::PERINGKAT_JUARA_3 => 'Juara 3',
        self::PERINGKAT_HARAPAN => 'Juara Harapan',
        self::PERINGKAT_FINALIS => 'Finalis',
        self::PERINGKAT_PESERTA => 'Peserta',
    ];

    /**
     * Peringkat yang TIDAK menghasilkan poin.
     *
     * Sekadar ikut serta bukan prestasi. Kalau peserta diberi poin, poin
     * berhenti mengukur apa pun — apalagi karena kehadiran pada kegiatan
     * internal sudah punya poinnya sendiri.
     *
     * @var array<int, string>
     */
    public const TANPA_POIN = [self::PERINGKAT_PESERTA];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'diverifikasi_pada' => 'datetime',
            'unggulan' => 'boolean',
            'tampil_publik' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(AchievementCategory::class, 'achievement_category_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERVERIFIKASI);
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DIAJUKAN);
    }

    /**
     * Yang boleh dilihat publik: sudah diperiksa DAN kader mengizinkan.
     */
    public function scopeTayangPublik(Builder $query): Builder
    {
        return $query->terverifikasi()->where('tampil_publik', true);
    }

    public function scopeTingkat(Builder $query, string $tingkat): Builder
    {
        return $query->where('tingkat', $tingkat);
    }

    /* ------------------------------------------------------------------ */
    /* Turunan                                                             */
    /* ------------------------------------------------------------------ */

    public function judulTeks(): string
    {
        return $this->getTranslation('judul', 'id') ?: 'Prestasi';
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function labelTingkat(): string
    {
        return self::TINGKAT[$this->tingkat] ?? $this->tingkat;
    }

    public function labelPeringkat(): string
    {
        return self::PERINGKAT[$this->peringkat] ?? $this->peringkat;
    }

    /**
     * Poin yang layak diterima prestasi ini — nol bila bukan juara.
     */
    public function poin(): int
    {
        if (in_array($this->peringkat, self::TANPA_POIN, true)) {
            return 0;
        }

        return self::POIN_TINGKAT[$this->tingkat] ?? 0;
    }

    public function terverifikasi(): bool
    {
        return $this->status === self::STATUS_TERVERIFIKASI;
    }

    /**
     * Sidik peristiwa untuk buku besar poin.
     */
    public static function sidik(int $achievementId, int $memberId): string
    {
        return 'prestasi:'.$achievementId.':anggota:'.$memberId;
    }

    public function periodeLabel(): string
    {
        return $this->tanggal?->format('Y-m') ?? now()->format('Y-m');
    }

    public static function kodeKategori(string $nama): string
    {
        return Str::upper(Str::slug($nama, '_')) ?: 'PRESTASI';
    }
}
