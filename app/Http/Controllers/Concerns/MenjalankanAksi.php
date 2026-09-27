<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Menjalankan aksi layanan dan mengubah galat validasinya menjadi pesan yang
 * terbaca pengurus.
 *
 * Layanan keuangan dan peminjaman menolak operasi tidak sah dengan
 * ValidationException (mis. "saldo tidak cukup", "alasan void wajib diisi").
 * Tanpa penangkap ini, pengurus hanya melihat halaman galat tanpa tahu apa yang
 * salah — padahal pesannya sudah ditulis dengan jelas di layanan.
 */
trait MenjalankanAksi
{
    /**
     * @param  callable(): mixed  $aksi
     */
    protected function jalankan(callable $aksi, string $pesanSukses): RedirectResponse
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', $pesanSukses);
    }
}
