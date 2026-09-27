<?php

namespace App\Models;

use App\Support\Audiens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

/**
 * Arsip dokumen (AD/ART, template surat, hasil rapat).
 *
 * KENDALI AKSES ADA DI `akses`, BUKAN DI `kategori`. Kategori hanya keterangan
 * untuk manusia; yang menentukan siapa boleh mengunduh adalah daftar audiens.
 * AD/ART lazimnya boleh dibaca siapa saja, sedangkan notulen rapat internal
 * kadang belum boleh keluar — dua-duanya berkategori "dokumen resmi".
 *
 * BERKAS FISIK TIDAK DISALIN KE TABEL INI. Ia tinggal di pustaka media dan
 * hanya id-nya yang dicatat, supaya satu berkas tidak tersimpan dua kali
 * dengan dua hak akses yang bisa saling bertentangan.
 */
#[Fillable([
    'slug',
    'judul',
    'keterangan',
    'kategori',
    'nomor',
    'tanggal_dokumen',
    'akses',
    'media_id',
    'tautan_luar',
    'period_id',
    'diunggah_oleh',
])]
#[Hidden(['deleted_at'])]
class Document extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['judul', 'keterangan'];

    public const KATEGORI_AD_ART = 'ad_art';

    public const KATEGORI_TEMPLATE_SURAT = 'template_surat';

    public const KATEGORI_HASIL_RAPAT = 'hasil_rapat';

    public const KATEGORI_LAINNYA = 'lainnya';

    /**
     * @var array<string, string>
     */
    public const KATEGORI = [
        self::KATEGORI_AD_ART => 'AD/ART & Anggaran Dasar',
        self::KATEGORI_TEMPLATE_SURAT => 'Template Surat',
        self::KATEGORI_HASIL_RAPAT => 'Hasil Rapat & Notulen',
        self::KATEGORI_LAINNYA => 'Lainnya',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'akses' => 'array',
            'tanggal_dokumen' => 'date',
        ];
    }

    public function periode()
    {
        return $this->belongsTo(Period::class, 'period_id');
    }

    public function pengunggah()
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function scopeKategori(Builder $query, string $kategori): Builder
    {
        return $query->where('kategori', $kategori);
    }

    public function scopeTerbaruDulu(Builder $query): Builder
    {
        return $query->orderByDesc('tanggal_dokumen')->orderByDesc('id');
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? $this->kategori;
    }

    public function judulTeks(): string
    {
        return $this->getTranslation('judul', 'id') ?: 'Dokumen';
    }

    public function keteranganTeks(): ?string
    {
        $teks = $this->getTranslation('keterangan', 'id');

        return $teks !== '' ? $teks : null;
    }

    /**
     * @return array<int, string>
     */
    public function audiensTeks(): array
    {
        return array_values(array_intersect_key(
            Audiens::PILIHAN,
            array_flip($this->akses ?? []),
        ));
    }

    public function bolehDibacaOleh(?User $pengguna): bool
    {
        return Audiens::boleh($this->akses, $pengguna);
    }

    public function terbukaUntukUmum(): bool
    {
        return in_array(Audiens::PUBLIK, $this->akses ?? [], true);
    }

    /**
     * BERKASNYA ADA? Dokumen boleh dicatat lebih dulu sebagai tautan, lalu
     * berkasnya diunggah menyusul — jadi "belum ada berkas" adalah keadaan yang
     * sah, bukan kerusakan.
     */
    public function punyaBerkas(): bool
    {
        return $this->media_id !== null || ($this->tautan_luar !== null && $this->tautan_luar !== '');
    }

    public function berkas(): ?Media
    {
        return $this->media_id !== null ? Media::query()->find($this->media_id) : null;
    }

    public function namaBerkas(): ?string
    {
        return $this->berkas()?->file_name;
    }

    /**
     * Ukuran berkas dalam satuan yang enak dibaca manusia.
     */
    public function ukuranTeks(): ?string
    {
        $ukuran = $this->berkas()?->size;

        if ($ukuran === null) {
            return null;
        }

        foreach (['B', 'KB', 'MB', 'GB'] as $i => $satuan) {
            if ($ukuran < 1024 ** ($i + 1)) {
                return round($ukuran / (1024 ** $i), $i === 0 ? 0 : 1).' '.$satuan;
            }
        }

        return round($ukuran / (1024 ** 3), 1).' GB';
    }

    public static function slugUnik(string $judul, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($judul) ?: 'dokumen';
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
