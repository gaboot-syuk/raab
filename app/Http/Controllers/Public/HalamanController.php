<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

/**
 * Halaman statis publik (Sejarah, Visi & Misi, Sambutan).
 *
 * Dirender server-side dengan Blade. Isinya berasal dari basis data dan
 * otomatis mengikuti bahasa yang aktif (id atau en).
 */
class HalamanController extends Controller
{
    public function sejarah(): View
    {
        return $this->tampilkan(Page::TIPE_SEJARAH);
    }

    public function visiMisi(): View
    {
        return $this->tampilkan(Page::TIPE_VISI_MISI);
    }

    public function sambutan(): View
    {
        return $this->tampilkan(Page::TIPE_SAMBUTAN);
    }

    private function tampilkan(string $kunci): View
    {
        $halaman = Page::berdasarkanKunci($kunci);

        abort_if($halaman === null, 404);

        return view('public.halaman', [
            'halaman' => $halaman,
            'kunciHalaman' => $kunci,
        ]);
    }
}
