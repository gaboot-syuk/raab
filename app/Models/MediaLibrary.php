<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
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
     * Apakah ada mesin gambar yang bisa dipakai.
     */
    protected function adaMesinGambar(): bool
    {
        return extension_loaded('gd') || extension_loaded('imagick');
    }

    /**
     * Thumbnail hanya dibuat bila GD atau Imagick tersedia.
     *
     * Lingkungan uji internal (sandbox) tidak dapat memasang GD, sedangkan
     * server produksi bisa. Dengan penjagaan ini, unggahan tetap berhasil di
     * dua lingkungan, dan thumbnail otomatis aktif begitu GD tersedia.
     *
     * Penjagaan ini dipisahkan menjadi metode tersendiri supaya uji dapat
     * memeriksa definisi konversinya TANPA GD (lihat MediaUnggahTest) — dan
     * justru di situlah letak masalahnya selama ini: karena baris di bawah
     * tidak pernah dijalankan di sini, salah tulis nama manipulasi tidak
     * pernah ketahuan sampai meledak di server.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if (! $this->adaMesinGambar()) {
            return;
        }

        /*
         * HANYA GAMBAR — dan ini menjaga dari 500, bukan sekadar merapikan.
         *
         * `PerformConversionAction::execute()` memanggil
         * `ImageGeneratorFactory::forMedia($media)->convert(...)` TANPA
         * memeriksa null, padahal `forMedia()` memang boleh mengembalikan null.
         * Untuk berkas yang tidak punya generator gambar — DOCX, XLSX, PPTX,
         * dan PDF yang butuh pustaka tambahan yang tidak terpasang di sini —
         * hasilnya:
         *
         *   Call to a member function convert() on null
         *
         * dan karena konversi berjalan SESUDAH berkasnya tersimpan, pengurus
         * melihat modal galat 500 padahal berkasnya sudah terunggah. Persis
         * bentuk kegagalan yang baru saja kita kejar.
         *
         * Tanpa penjagaan ini, konversi `kecil` berlaku untuk SEMUA koleksi
         * (medialibrary memperlakukan daftar koleksi yang kosong sebagai
         * "semua"), sehingga mengunggah satu berkas Word lewat Pustaka Media
         * sudah cukup untuk memicunya.
         */
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        /*
         * `fit(Fit::Max, 480, 480)` — BUKAN `shrinkOnly()`.
         *
         * Di sini dulu tertulis `->shrinkOnly()`, dan metode itu TIDAK ADA
         * pada `Spatie\Image\Image`. Manipulasi medialibrary diteruskan apa
         * adanya (`Manipulations::apply()` memanggil `$image->$nama(...)`),
         * jadi hasilnya bukan galat saat ditulis melainkan galat saat dipakai:
         *
         *   Call to undefined method Spatie\Image\Image::shrinkOnly()
         *
         * dan karena `createDerivedFiles()` berjalan SESUDAH berkasnya
         * tersimpan tanpa dibungkus try/catch, pengurus melihat modal galat
         * 500 padahal gambarnya sudah terunggah.
         *
         * Yang dimaksud sebenarnya adalah "muatkan dalam kotak 480×480, jangan
         * diperbesar". Itu tepat arti `Fit::Max`, dan pustakanya sendiri yang
         * menjaminnya (Spatie\Image\Enums\Fit.php):
         *
         *   Fit::Fill, Fit::Max, Fit::FillMax => [PreserveAspectRatio, DoNotUpsize]
         *
         * Memanggil `width(480)->height(480)` berurutan TIDAK sama: pada
         * gambar yang lebar, panggilan kedua justru MEMPERBESAR gambarnya.
         *
         * DIANTREKAN, BUKAN DI DALAM PERMINTAAN UNGGAH.
         *
         * Sebelumnya di sini ada `->nonQueued()`, dan itu menaruh pekerjaan
         * gambar paling berat di dalam permintaan unggah. Urutan di dalam
         * pustaka medialibrary-lah yang membuatnya berbahaya
         * (`MediaCollections\Filesystem::add()`):
         *
         *   copyToMediaLibrary()      → berkas TERSIMPAN
         *   event(MediaHasBeenAdded)  → pendengar kita (sudah dibungkus try/catch)
         *   createDerivedFiles()      → KONVERSI, tanpa try/catch
         *
         * Kegagalan konversi karena itu selalu meledak SESUDAH baris media dan
         * berkasnya tersimpan: yang dilihat pengurus adalah modal galat 500,
         * tetapi begitu halaman disegarkan gambarnya ADA. Gejala itu tidak akan
         * hilang hanya dengan memperbaiki salah tulis di atas — selama
         * konversinya berjalan di dalam permintaan, kegagalan gambar apa pun
         * (memori habis, berkas terlalu besar) tetap menjadi 500 yang tidak
         * bisa dicegat di sini.
         *
         * Untuk berkas di disk jarak jauh langkahnya lebih berat lagi: spatie
         * menyalin berkasnya lebih dulu dari R2 ke direktori sementara
         * (`FileManipulator::performConversions`), baru GD membuka gambar penuh
         * di memori. Peramban pengurus tidak perlu menunggu semua itu.
         *
         * Produksi menjalankan pekerja antrean di dalam wadah yang sama
         * (`docker/prod/supervisord.conf`), jadi thumbnailnya tetap muncul —
         * hanya tidak lagi menahan unggahan. Kalau konversinya gagal,
         * kegagalannya tercatat di log pekerja dan panel memakai berkas
         * aslinya (lihat `hasGeneratedConversion()` di MediaController).
         */
        $this->addMediaConversion('kecil')
            ->fit(Fit::Max, 480, 480)
            ->queued();
    }
}
