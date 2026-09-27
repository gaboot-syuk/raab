<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable(['nama', 'slug', 'deskripsi', 'urutan', 'aktif'])]
class ArticleCategory extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['nama', 'slug', 'deskripsi'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function artikel(): HasMany
    {
        return $this->hasMany(Article::class, 'kategori_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    /**
     * Jumlah artikel terbit pada kategori ini.
     *
     * Memanggil ini SATU PER SATU di dalam perulangan berarti satu query per
     * kategori — sepuluh kategori, sepuluh query, hanya untuk angka di sebelah
     * nama. Di laptop tidak terasa; di server gratis dengan basis data jauh,
     * justru inilah yang membuat halaman lambat.
     *
     * Karena itu halaman yang menampilkan daftar kategori WAJIB memakai
     * `denganJumlahTerbit()`, yang mengambil seluruh angkanya dalam satu
     * query. Metode ini tetap ada sebagai cadangan untuk pemakaian tunggal.
     */
    public function jumlahTerbit(): int
    {
        return $this->artikel()->terbit()->count();
    }

    /**
     * Daftar kategori aktif, lengkap dengan jumlah artikel terbitnya — dalam
     * SATU query tambahan, bukan satu per kategori.
     *
     * Angkanya ditaruh di atribut `jumlah_terbit` supaya view memakai nilai
     * yang sudah dihitung, bukan memicu query baru.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function denganJumlahTerbit(): \Illuminate\Database\Eloquent\Collection
    {
        $kategori = static::query()->aktif()->get();

        $jumlah = Article::query()
            ->terbit()
            ->whereIn('tipe', Article::TIPE_PUBLIK)
            ->whereNotNull('kategori_id')
            ->groupBy('kategori_id')
            ->pluck(DB::raw('count(*)'), 'kategori_id');

        foreach ($kategori as $satu) {
            $satu->setAttribute('jumlah_terbit', (int) ($jumlah[$satu->id] ?? 0));
        }

        return $kategori;
    }
}
