<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Artikel: berita, opini, kajian, esai, sastra, dan berita acara.
 *
 * ALUR STATUS
 *   draf → menunggu_review → terbit
 *                          ↘ perlu_revisi → menunggu_review lagi
 *                          ↘ ditolak
 *
 * Berita acara memakai alur berbeda: diterbitkan langsung oleh Sekretaris tanpa
 * melewati review, karena dokumennya bersifat administratif.
 */
#[Fillable([
    'user_id', 'kategori_id', 'cover_media_id', 'tipe', 'status',
    'judul', 'slug', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi',
    'dijadwalkan_pada', 'terbit_pada', 'unggulan', 'dilihat', 'waktu_baca_menit',
    'catatan_review', 'reviewer_id', 'direview_pada',
    'nomor_dokumen', 'tanggal_agenda', 'agenda', 'keputusan',
    'penandatangan', 'jabatan_penandatangan',
])]
class Article extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;
    use SoftDeletes;

    public const TIPE_BERITA = 'berita';
    public const TIPE_OPINI = 'opini';
    public const TIPE_KAJIAN = 'kajian';
    public const TIPE_ESAI = 'esai';
    public const TIPE_SASTRA = 'sastra';
    public const TIPE_BERITA_ACARA = 'berita_acara';

    public const STATUS_DRAF = 'draf';
    public const STATUS_MENUNGGU = 'menunggu_review';
    public const STATUS_REVISI = 'perlu_revisi';
    public const STATUS_TERBIT = 'terbit';
    public const STATUS_DITOLAK = 'ditolak';

    /**
     * Lima tipe publikasi + berita acara (khusus Sekretaris).
     *
     * @var array<string, string>
     */
    public const TIPE = [
        self::TIPE_BERITA => 'Berita',
        self::TIPE_OPINI => 'Opini',
        self::TIPE_KAJIAN => 'Kajian',
        self::TIPE_ESAI => 'Esai',
        self::TIPE_SASTRA => 'Sastra',
        self::TIPE_BERITA_ACARA => 'Berita Acara',
    ];

    /**
     * Tipe yang punya halaman publik.
     *
     * @var array<int, string>
     */
    public const TIPE_PUBLIK = [
        self::TIPE_BERITA,
        self::TIPE_OPINI,
        self::TIPE_KAJIAN,
        self::TIPE_ESAI,
        self::TIPE_SASTRA,
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS = [
        self::STATUS_DRAF => 'Draf',
        self::STATUS_MENUNGGU => 'Menunggu Review',
        self::STATUS_REVISI => 'Perlu Revisi',
        self::STATUS_TERBIT => 'Terbit',
        self::STATUS_DITOLAK => 'Ditolak',
    ];

    /**
     * Jumlah revisi yang disimpan per artikel.
     */
    public const MAKS_REVISI = 10;

    public array $translatable = [
        'judul', 'slug', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dijadwalkan_pada' => 'datetime',
            'terbit_pada' => 'datetime',
            'direview_pada' => 'datetime',
            'tanggal_agenda' => 'date',
            'unggulan' => 'boolean',
            'dilihat' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'kategori_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(ArticleRevision::class)->latest();
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Artikel yang boleh tampil publik saat ini.
     *
     * Termasuk artikel terjadwal yang waktunya sudah lewat, sehingga berita
     * otomatis muncul tanpa campur tangan pengurus.
     */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERBIT)
            ->where(fn (Builder $q) => $q
                ->whereNull('terbit_pada')
                ->orWhere('terbit_pada', '<=', now()));
    }

    public function scopeTipe(Builder $query, string $tipe): Builder
    {
        return $query->where('tipe', $tipe);
    }

    public function scopeUnggulan(Builder $query): Builder
    {
        return $query->where('unggulan', true);
    }

    public function scopeTerbaru(Builder $query): Builder
    {
        return $query->orderByDesc('terbit_pada')->orderByDesc('id');
    }

    /**
     * Artikel terbit yang versi Inggrisnya belum lengkap — dipakai panel untuk
     * menampilkan pekerjaan terjemahan yang masih tertunggak.
     */
    public function scopeBelumDiterjemahkan(Builder $query): Builder
    {
        return $query->terbit()
            ->where(fn (Builder $q) => $q
                ->whereNull('judul->en')
                ->orWhere('judul->en', '')
                ->orWhereNull('konten->en')
                ->orWhere('konten->en', ''));
    }

    /* ------------------------------------------------------------------ */
    /* Bantuan                                                             */
    /* ------------------------------------------------------------------ */

    public function labelTipe(): string
    {
        return self::TIPE[$this->tipe] ?? ucfirst($this->tipe);
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function adalahBeritaAcara(): bool
    {
        return $this->tipe === self::TIPE_BERITA_ACARA;
    }

    /**
     * Sudah waktunya terbit? Dipakai penjadwal dan penanda di panel.
     */
    public function siapTerbit(): bool
    {
        if ($this->status !== self::STATUS_TERBIT) {
            return false;
        }

        return $this->terbit_pada === null || $this->terbit_pada->isPast();
    }

    /**
     * Persentase kelengkapan terjemahan Inggris (dari 3 kolom utama).
     */
    public function kelengkapanTerjemahan(): int
    {
        $kolom = ['judul', 'ringkasan', 'konten'];
        $terisi = 0;

        foreach ($kolom as $nama) {
            if (filled($this->getTranslation($nama, 'en', false))) {
                $terisi++;
            }
        }

        return (int) round($terisi / count($kolom) * 100);
    }

    /**
     * Perkiraan waktu baca (menit), dihitung dari jumlah kata isi Indonesia.
     */
    public function hitungWaktuBaca(): int
    {
        $teks = strip_tags((string) $this->getTranslation('konten', 'id', false));
        $kata = str_word_count($teks);

        return max(1, (int) ceil($kata / 200));
    }

    /**
     * Slug otomatis bila pengurus mengosongkannya.
     */
    public static function buatSlug(string $judul): string
    {
        return Str::slug($judul) ?: 'artikel';
    }
}
