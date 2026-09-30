<?php

namespace App\Support;

use Intervention\Image\Drivers\Gd\Driver as DriverGd;
use Intervention\Image\Drivers\Imagick\Driver as DriverImagick;
use Intervention\Image\ImageManager;

/**
 * Mesin pengecil gambar.
 *
 * KENAPA DIPISAH DARI PENDENGARNYA
 *
 * Bagian ini BUTUH ekstensi GD. Produksi memilikinya, tetapi wadah
 * pengembangan di mesin ini tidak — dan citra itu tidak dapat dibangun ulang
 * dari sini karena jaringannya tertutup. Kalau seluruh logika pengecilan
 * disatukan di pendengar, keputusannya (kapan berkas diganti, kapan
 * dibiarkan, bagaimana ukuran pada catatan media diperbarui) tidak akan
 * pernah teruji di sini — hanya di produksi, tempat kegagalannya paling mahal.
 *
 * Dengan pemisahan ini, penggantian berkas dan pembaruan ukuran diuji memakai
 * mesin palsu, sedangkan mesin sungguhannya diuji terpisah dan akan berjalan
 * di mana pun GD tersedia.
 */
class PemrosesGambar
{
    /** Sisi terpanjang maksimum, dalam piksel. */
    public const MAKS_SISI = 1600;

    /** Mutu simpan ulang untuk format yang mendukungnya. */
    public const MUTU = 82;

    /**
     * Nama KELAS pengandar gambar yang akan dipakai.
     *
     * JANGAN mengisi ini dengan julukan seperti 'gd'.
     *
     * Percobaan pertama saya menulis `new ImageManager('gd')`, dan itu TERBACA
     * masuk akal — dokumentasi lama memang memakai julukan. Tetapi v4 tidak
     * menerima julukan: `CanResolveDriver::resolveDriver()` menolak apa pun
     * yang bukan nama kelas yang benar-benar ada, dengan pesan
     * "Argument $driver must be existing class name".
     *
     * Kegagalannya tidak pernah terlihat sebagai galat besar: pendengar
     * pengecil gambar menangkapnya dan hanya menulis satu baris WARNING,
     * sedangkan wadah pengembangan memang tidak punya GD sehingga baris itu
     * tidak pernah muncul di sini. Pengecilan gambar karena itu TIDAK PERNAH
     * bekerja sekali pun, dan tidak ada yang tahu.
     *
     * Sekarang nama pengandar diambil dari kelas aslinya, sehingga salah tulis
     * tidak mungkin lagi — dan `MediaUnggahTest` memeriksa bahwa nama ini
     * memang kelas yang ada dan mengimplementasikan DriverInterface.
     */
    public static function pengandar(): string
    {
        return extension_loaded('imagick') ? DriverImagick::class : DriverGd::class;
    }

    /**
     * Kembalikan isi gambar yang sudah diperkecil, atau null bila tidak ada
     * yang perlu dikerjakan.
     *
     * @return string|null  Byte gambar baru, atau null bila dibiarkan apa adanya.
     */
    public function perkecil(string $isi, string $ekstensi): ?string
    {
        $gambar = (new ImageManager(self::pengandar()))->decodeBinary($isi);

        // Sudah cukup kecil: menyimpan ulang hanya menurunkan mutunya tanpa
        // menghemat ruang yang berarti.
        if (max($gambar->width(), $gambar->height()) <= self::MAKS_SISI) {
            return null;
        }

        $gambar->scaleDown(width: self::MAKS_SISI, height: self::MAKS_SISI);

        // PNG tidak menerima opsi `quality`, jadi ia disimpan dengan bawaannya.
        $keluaran = $ekstensi === 'png'
            ? $gambar->encodeUsingFileExtension('png')
            : $gambar->encodeUsingFileExtension($ekstensi, quality: self::MUTU);

        return $keluaran->toString();
    }
}
