<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Pengumuman;
use App\Support\Pengaturan;
use Illuminate\View\View;

/**
 * Pengumuman publik.
 *
 * Hanya pengumuman bertipe `publik` yang sudah berlaku DAN membuka diri untuk
 * audiens umum. Halaman ini sengaja tidak menampilkan pengumuman internal —
 * menyembunyikannya di tampilan bukan pilihan, karena yang tidak berhak memang
 * tidak pernah dikirim ke peramban.
 */
class PengumumanController extends Controller
{
    public function __construct(private Pengumuman $pengumuman) {}

    public function index(): View
    {
        return view('public.pengumuman', [
            'situs' => Pengaturan::semua(),
            'daftar' => $this->pengumuman->untukPublik(),
        ]);
    }
}
