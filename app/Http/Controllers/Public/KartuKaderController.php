<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MemberCard;
use Illuminate\View\View;

/**
 * Verifikasi kartu kader — PUBLIK, tanpa login.
 *
 * Yang diperiksa halaman ini adalah KARTU, bukan orang. Jawabannya hanya satu
 * dari dua: kartu ini asli dan masih berlaku, atau tidak. Itu cukup bagi
 * panitia acara, petugas perpustakaan, atau mitra yang memegang kartunya.
 *
 * KARENA TERBUKA UNTUK UMUM, halaman ini hanya menampilkan apa yang memang
 * sudah tercetak di kartu: nama, nomor anggota, unit, dan masa berlaku.
 * Telepon, alamat, dan email anggota TIDAK PERNAH ikut ditampilkan — kalau
 * ikut, siapa pun yang menemukan kartu jatuh bisa memanen data pribadi
 * pemiliknya hanya dengan memindai kode QR.
 *
 * Token berupa 48 karakter acak, jadi nomor anggota tidak dapat ditebak
 * menjadi tautan verifikasi.
 */
class KartuKaderController extends Controller
{
    /**
     * Sebab yang tidak melekat pada kartu mana pun.
     *
     * `MemberCard::alasanTidakSah()` hanya bisa menjawab untuk kartu yang ADA.
     * Token yang salah ketik adalah kasus paling sering terjadi, dan tanpa
     * sebab ini halaman verifikasi hanya berkata "tidak dapat diverifikasi"
     * tanpa menjelaskan apa yang harus diperiksa.
     */
    public const ALAS_TIDAK_DITEMUKAN = 'tidak_ditemukan';

    public function __invoke(string $token): View
    {
        $kartu = MemberCard::query()
            ->with(['member.unit'])
            ->where('token', $token)
            ->first();

        if ($kartu === null) {
            return view('public.verifikasi-kader', [
                'kartu' => null,
                'sah' => false,
                'alasan' => self::ALAS_TIDAK_DITEMUKAN,
            ]);
        }

        $alasan = $kartu->alasanTidakSah();

        return view('public.verifikasi-kader', [
            'kartu' => $kartu,
            'sah' => $alasan === null,
            'alasan' => $alasan,
        ]);
    }
}
