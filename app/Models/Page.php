<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable([
    'kunci',
    'slug',
    'judul',
    'ringkasan',
    'konten',
    'seo_judul',
    'seo_deskripsi',
    'tipe',
    'status',
    'terbit_pada',
    'media_id',
    'dibuat_oleh',
    'diperbarui_oleh',
])]
class Page extends Model
{
    use HasFactory;
    use HasTranslations;
    use TerjemahanSaatSerialisasi;
    use SoftDeletes;

    public const TIPE_SEJARAH = 'sejarah';

    public const TIPE_VISI_MISI = 'visi_misi';

    public const TIPE_SAMBUTAN = 'sambutan';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_TERBIT = 'terbit';

    /** @var array<int, string> */
    public array $translatable = [
        'slug',
        'judul',
        'ringkasan',
        'konten',
        'seo_judul',
        'seo_deskripsi',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terbit_pada' => 'datetime',
        ];
    }

    /**
     * Hanya halaman yang sudah terbit.
     */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TERBIT)
            ->where(fn (Builder $q) => $q->whereNull('terbit_pada')->orWhere('terbit_pada', '<=', now()));
    }

    /**
     * Ambil halaman tetap berdasarkan kunci (sejarah, visi_misi, sambutan).
     *
     * Hasilnya di-CACHE. Tiga halaman ini isinya jarang berubah tetapi dibuka
     * berkali-kali, dan tiap pembukaan tanpa cache berarti satu query ke basis
     * data hanya untuk teks yang sudah lama tidak disunting.
     *
     * YANG DISIMPAN ADALAH ATRIBUT MENTAH, BUKAN MODEL.
     *
     * Pengandar cache `database` — yang dipakai di produksi — membatasi kelas
     * yang boleh dikembalikan dari cache. Model yang disimpan akan kembali
     * sebagai `__PHP_Incomplete_Class`, dan halaman gagal dengan galat tipe.
     * Di pengujian hal ini tidak terlihat karena pengujian memakai pengandar
     * `array` yang tidak membatasi apa pun.
     *
     * Menyimpan atribut mentah juga membuat cache ini aman untuk dwibahasa:
     * judul dan isi tetap tersimpan sebagai JSON dua bahasa, sehingga satu
     * entri cache melayani kedua bahasa dengan benar. Kalau yang disimpan
     * adalah hasil `toArray()`, isinya sudah terkunci pada bahasa saat cache
     * dibuat.
     */
    public static function berdasarkanKunci(string $kunci): ?self
    {
        $mentah = Cache::rememberForever(
            self::kunciCache($kunci),
            function () use ($kunci): ?array {
                $halaman = static::query()->terbit()->where('kunci', $kunci)->first();

                return $halaman?->getAttributes();
            },
        );

        if ($mentah === null) {
            return null;
        }

        return (new static)->newFromBuilder($mentah);
    }

    /**
     * Buang cache halaman. WAJIB dipanggil setiap kali halaman disunting,
     * kalau tidak pengunjung akan terus melihat isi yang lama.
     */
    public static function lupakan(string $kunci): void
    {
        Cache::forget(self::kunciCache($kunci));
    }

    public static function lupakanSemua(): void
    {
        foreach ([self::TIPE_SEJARAH, self::TIPE_VISI_MISI, self::TIPE_SAMBUTAN] as $kunci) {
            self::lupakan($kunci);
        }
    }

    private static function kunciCache(string $kunci): string
    {
        return 'halaman-'.$kunci;
    }

    /**
     * Persentase kelengkapan terjemahan Inggris (0–100).
     * Dipakai panel untuk menandai konten yang belum diterjemahkan.
     */
    public function kelengkapanTerjemahan(): int
    {
        $bidang = ['judul', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi'];
        $terisi = 0;

        foreach ($bidang as $kolom) {
            $nilai = $this->getTranslation($kolom, 'en', false);

            if (is_string($nilai) && trim($nilai) !== '') {
                $terisi++;
            }
        }

        return (int) round(($terisi / count($bidang)) * 100);
    }
}
