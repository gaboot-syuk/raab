<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\KontakRequest;
use App\Models\ContactMessage;
use App\Models\SocialLink;
use App\Support\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Halaman layanan publik: Kontak, Lokasi Sekretariat, dan Media Sosial.
 *
 * Semuanya membaca data dari Pengaturan Situs sehingga pengurus dapat
 * memperbarui tanpa mengubah kode.
 */
class LayananController extends Controller
{
    public function kontak(): View
    {
        return view('public.kontak', [
            'situs' => Pengaturan::semua(),
            'sosmed' => SocialLink::aktifTerCache(),
            'jenis' => ContactMessage::JENIS,
        ]);
    }

    /**
     * Terima pesan dari formulir kontak.
     *
     * Pesan disimpan lebih dulu (bukan langsung dikirim lewat email) supaya
     * tidak ada pesan yang hilang bila pengiriman email bermasalah.
     */
    public function kirimKontak(KontakRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['website'], $data['setuju']);

        ContactMessage::query()->create([
            ...$data,
            'status' => ContactMessage::STATUS_BARU,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return redirect()
            ->route('public.kontak')
            ->with('sukses', __('umum.kontak.sukses'));
    }

    public function lokasi(): View
    {
        return view('public.lokasi', [
            'situs' => Pengaturan::semua(),
            'sosmed' => SocialLink::aktifTerCache(),
        ]);
    }

    public function mediaSosial(): View
    {
        return view('public.media-sosial', [
            'situs' => Pengaturan::semua(),
            'sosmed' => SocialLink::aktifTerCache(),
        ]);
    }
}
