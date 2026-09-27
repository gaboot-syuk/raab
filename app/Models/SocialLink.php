<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Models\Concerns\TerjemahanSaatSerialisasi;
use Spatie\Translatable\HasTranslations;

#[Fillable(['platform', 'label', 'url', 'ikon', 'urutan', 'aktif'])]
class SocialLink extends Model
{
    use HasTranslations;
    use TerjemahanSaatSerialisasi;

    public array $translatable = ['label'];

    private const KUNCI_CACHE = 'sosmed-aktif';

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

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true)->orderBy('urutan');
    }

    /**
     * Tautan media sosial yang aktif, DI-CACHE.
     *
     * Daftar ini dirender di KAKI SETIAP halaman publik. Tanpa cache, satu
     * halaman berarti satu query ke basis data hanya untuk deretan ikon yang
     * isinya nyaris tidak pernah berubah — dan itu terjadi di setiap kunjungan,
     * di setiap halaman.
     *
     * YANG DISIMPAN ADALAH ATRIBUT MENTAH, BUKAN MODEL.
     *
     * Ini bukan soal selera, dan bukan penghematan. Pengandar cache `database`
     * — yang dipakai di produksi — membatasi kelas yang boleh dikembalikan dari
     * cache. Model yang disimpan akan kembali sebagai `__PHP_Incomplete_Class`,
     * dan halaman gagal dengan galat tipe. Di lingkungan pengujian hal ini
     * tidak terlihat karena pengujian memakai pengandar `array` yang tidak
     * membatasi apa pun.
     *
     * Karena itu barisnya disimpan sebagai larik atribut mentah, lalu dibangun
     * ulang menjadi model lewat `hydrate()`. Ada untung sampingan yang penting:
     * nilai terjemahan tetap MENTAH di dalam cache, sehingga satu entri cache
     * melayani bahasa Indonesia dan Inggris dengan benar. Kalau yang disimpan
     * adalah hasil `toArray()`, labelnya sudah terkunci pada bahasa saat cache
     * dibuat — dan halaman Inggris akan menampilkan label Indonesia.
     *
     * Masa berlakunya DIBATASI (satu jam), bukan selamanya. Alasannya jujur:
     * sampai sekarang belum ada halaman panel untuk menyunting tautan ini, jadi
     * tidak ada tempat yang pasti untuk membuang cache-nya saat berubah. Kalau
     * kelak panelnya dibuat, panggil `lupakan()` di sana dan masa berlaku ini
     * bisa dihapus.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function aktifTerCache(): \Illuminate\Database\Eloquent\Collection
    {
        $mentah = Cache::remember(
            self::KUNCI_CACHE,
            now()->addHour(),
            fn (): array => static::query()->aktif()->get()
                ->map(fn (self $tautan): array => $tautan->getAttributes())
                ->all(),
        );

        return static::hydrate($mentah);
    }

    /**
     * Buang cache. WAJIB dipanggil setiap kali tautan disunting di panel.
     */
    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }
}
