<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Arsip;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as ResponsHttp;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arsip dokumen — halaman publik dan jalur unduhan.
 *
 * JALUR UNDUHAN ADALAH SATU-SATUNYA PINTU KELUARNYA BERKAS. Berkas arsip
 * disimpan di disk privat (`local`), di luar folder yang dilayani web, jadi
 * alamat `/storage/...` tidak bisa dipakai untuk melewati pemeriksaan audiens.
 * Satu jalur untuk tamu maupun anggota: yang membedakan hanya hasil pemeriksaan
 * `bolehUnduh()`.
 */
class ArsipController extends Controller
{
    public function __construct(private Arsip $arsip) {}

    public function index(): View
    {
        return view('public.arsip', [
            'situs' => Pengaturan::semua(),
            'daftar' => $this->arsip->untukPublik(),
        ]);
    }

    /**
     * Unduh berkas dokumen.
     *
     * Pemeriksaan audiens ada DI SINI, bukan hanya di halaman daftar: daftar
     * yang sudah disaring tetap bisa ditembus lewat alamat langsung, dan itulah
     * yang paling mudah terlewat.
     */
    public function unduh(Request $request, Document $dokumen): ResponsHttp|StreamedResponse
    {
        $pengguna = $request->user();

        abort_unless($this->arsip->bolehUnduh($dokumen, $pengguna), 403);

        $berkas = $dokumen->berkas();

        if ($berkas !== null) {
            $jalur = $berkas->getPathRelativeToRoot();

            abort_unless(Storage::disk('local')->exists($jalur), 404, 'Berkas dokumen ini tidak ditemukan di penyimpanan.');

            return Storage::disk('local')->download($jalur, $berkas->file_name);
        }

        // Dokumen boleh dicatat sebagai tautan saja (mis. berkasnya disimpan di
        // repositori lain). Tautannya dibuka langsung, dan pemeriksaan audiens
        // di atas sudah terjadi lebih dulu.
        abort_if($dokumen->tautan_luar === null, 404, 'Dokumen ini belum punya berkas maupun tautan.');

        return redirect()->away($dokumen->tautan_luar);
    }
}
