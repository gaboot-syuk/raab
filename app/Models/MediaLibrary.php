<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Wadah tunggal pustaka media.
 *
 * Semua berkas yang diunggah pengurus menempel pada satu baris (id = 1),
 * sehingga dapat dipakai ulang lintas modul: gambar halaman, poster event,
 * cover buku, atau bukti transaksi.
 */
#[Fillable([])]
class MediaLibrary extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const INDUK_ID = 1;

    public const KOLEKSI = [
        'gambar' => 'Gambar',
        'dokumen' => 'Dokumen',
        'logo' => 'Logo',
        'lainnya' => 'Lainnya',
        /*
         * Arsip dokumen TIDAK memakai salah satu koleksi di atas.
         *
         * Koleksi `berkas`/`dokumen` disimpan di disk `public`, sehingga siapa
         * pun yang menebak alamatnya bisa mengunduh berkasnya langsung — dan
         * kendali akses per audiens di modul Arsip jadi sekadar hiasan. Karena
         * itu berkas arsip disimpan di disk `local` (di luar folder publik) dan
         * hanya bisa keluar lewat jalur unduhan yang memeriksa audiensnya.
         */
        'arsip' => 'Arsip Dokumen (privat)',
    ];

    /**
     * Ambil (atau buat) baris wadah.
     *
     * ID diisi langsung, bukan lewat mass assignment, karena Laravel 13
     * menolak pengisian kolom yang tidak tercantum pada #[Fillable].
     */
    public static function induk(): self
    {
        $induk = self::query()->find(self::INDUK_ID);

        if ($induk) {
            return $induk;
        }

        $induk = new self;
        $induk->id = self::INDUK_ID;
        $induk->save();

        return $induk;
    }

    public function registerMediaCollections(): void
    {
        // Disk "public" agar berkas dapat diakses langsung dari web.
        $this->addMediaCollection('berkas')->useDisk('public');

        /*
         * Arsip dokumen ada di disk privat. Bandingkan dengan `berkas` di atas:
         * yang ini SENGAJA tidak bisa dibuka lewat alamat langsung, karena
         * dokumennya memang dibatasi per audiens.
         */
        $this->addMediaCollection('arsip')->useDisk('local');
    }

    /**
     * Thumbnail hanya dibuat bila GD atau Imagick tersedia.
     *
     * Lingkungan uji internal (sandbox) tidak dapat memasang GD, sedangkan
     * server produksi bisa. Dengan penjagaan ini, unggahan tetap berhasil di
     * dua lingkungan, dan thumbnail otomatis aktif begitu GD tersedia.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            return;
        }

        $this->addMediaConversion('kecil')
            ->width(480)
            ->height(480)
            ->shrinkOnly()
            ->nonQueued();
    }
}
