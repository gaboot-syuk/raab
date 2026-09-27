<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kartu kader digital milik anggota yang sedang masuk.
 *
 * Halaman ini SENGAJA berdiri sendiri (tanpa layout publik) karena tujuannya
 * dicetak atau disimpan sebagai PDF — menu dan footer hanya akan mengotori
 * hasil cetaknya. Pola yang sama sudah dipakai kartu peserta Mapaba/PKD.
 *
 * Kartu yang sudah dicabut atau kedaluwarsa TETAP ditampilkan, tetapi dengan
 * keterangan jelas. Menyembunyikannya akan membuat kader mengira kartunya
 * hilang, lalu menghubungi sekretariat untuk hal yang sebenarnya bisa
 * dijelaskan di halaman ini.
 */
class KartuController extends Controller
{
    public function __invoke(Request $request): View
    {
        $anggota = $request->user()->member;
        $kartu = $anggota?->kartu;

        return view('public.kartu-kader', [
            'situs' => Pengaturan::semua(),
            'anggota' => $anggota,
            'kartu' => $kartu,
            'alasan' => $kartu?->alasanTidakSah(),
            'tautanVerifikasi' => $kartu ? url('/verifikasi-kader/'.$kartu->token) : null,
        ]);
    }
}
