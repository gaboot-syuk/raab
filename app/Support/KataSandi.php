<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Aturan kata sandi — SATU-SATUNYA tempat kebijakannya ditulis.
 *
 * Sebelumnya aturan ini disalin di tiga tempat (pendaftaran, tambah pengguna,
 * ubah pengguna). Tiga salinan berarti cepat atau lambat ada satu yang
 * tertinggal saat kebijakannya diperketat — dan yang tertinggal itu justru
 * pintu masuk yang paling longgar.
 */
final class KataSandi
{
    /** Panjang minimum. Dua belas, bukan delapan: ini akun yang bisa mengubah data organisasi. */
    public const PANJANG_MINIMUM = 12;

    /**
     * Aturan untuk validasi Laravel.
     *
     * `uncompromised()` memeriksa kata sandi ke layanan HaveIBeenPwned lewat
     * jaringan. Itu berguna di produksi, tetapi membuat pengembangan dan
     * pengujian bergantung pada koneksi luar — jadi hanya dipasang di produksi.
     */
    public static function aturan(): Password
    {
        $aturan = Password::min(self::PANJANG_MINIMUM)->letters()->numbers();

        return app()->isProduction() ? $aturan->uncompromised() : $aturan;
    }

    /**
     * Keterangan singkat untuk ditulis di bawah kolom kata sandi.
     */
    public static function keterangan(): string
    {
        return 'Minimal '.self::PANJANG_MINIMUM.' karakter dan memuat huruf serta angka.';
    }
}
