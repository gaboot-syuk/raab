<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksa sambungan basis data.
 *
 * Dipisahkan dari pengendalinya supaya kegagalan sambungan dapat DIUJI tanpa
 * mengubah keadaan global aplikasi.
 *
 * Percobaan pertama saya menguji cabang kegagalannya dengan mengganti
 * `database.default` menjadi nama pengandar yang tidak ada. Itu berhasil
 * membuat sambungannya gagal, tetapi juga meninggalkan transaksi uji
 * (`RefreshDatabase`) dalam keadaan terbuka: enam uji berikutnya tumbang dengan
 * "cannot start a transaction within a transaction". Uji yang merusak uji lain
 * lebih buruk daripada uji yang tidak ada.
 *
 * Dengan kelas kecil ini, cabang kegagalannya diuji dengan menyuntikkan
 * pemeriksa palsu — tidak ada keadaan global yang disentuh.
 */
class PemeriksaBasisData
{
    /**
     * Apakah basis data benar-benar dapat disentuh.
     */
    public function terjangkau(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
