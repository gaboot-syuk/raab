<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Cadangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;
use Symfony\Component\HttpFoundation\Response as ResponsHttp;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Panel cadangan — KHUSUS SUPERADMIN.
 *
 * Halaman ini hanya MENGUNDUH dan MEMBUAT. Tidak ada tombol "pulihkan" di
 * antarmuka, dan itu keputusan yang disengaja: memulihkan berarti menghapus
 * seluruh data yang ada sekarang. Tindakan sebesar itu tidak boleh bisa
 * dilakukan karena salah klik di halaman web — ia dilakukan lewat perintah
 * `php artisan cadangan:pulihkan`, yang menuntut nama berkasnya diketik ulang.
 *
 * Pembatasan lewat peran `superadmin`, bukan lewat izin tersendiri, karena
 * berkas cadangan memuat SELURUH isi basis data: data pribadi anggota, bukti
 * pembayaran, dan hash kata sandi. Tidak ada peran lain yang boleh menyentuhnya.
 */
class CadanganController extends Controller
{
    public function __construct(private Cadangan $cadangan) {}

    public function index(): ResponsInertia
    {
        return Inertia::render('Panel/Cadangan/Index', [
            'daftar' => $this->cadangan->daftar(),
            'pengandar' => config('database.default'),
            'simpanBerkas' => Cadangan::SIMPAN_BERKAS,
            'catatan' => 'Berkas cadangan memuat SELURUH isi basis data — data pribadi anggota, bukti pembayaran, dan hash kata sandi. Unduh lalu simpan di luar server: berkas yang hanya ada di server yang sama tidak melindungi dari server itu hilang. Pemulihan sengaja tidak disediakan lewat halaman ini, karena memulihkan berarti menghapus seluruh data yang ada sekarang.',
        ]);
    }

    public function buat(): RedirectResponse
    {
        try {
            $hasil = $this->cadangan->buat();
        } catch (\Throwable $e) {
            return back()->with('galat', 'Cadangan gagal dibuat: '.$e->getMessage());
        }

        $dibersihkan = $this->cadangan->bersihkan();

        return back()->with('sukses', 'Cadangan '.$hasil['berkas'].' dibuat ('.$hasil['tabel'].' tabel, '.$hasil['baris'].' baris).'
            .($dibersihkan > 0 ? ' '.$dibersihkan.' cadangan lama dibersihkan.' : ''));
    }

    public function unduh(Request $request, string $berkas): ResponsHttp|StreamedResponse
    {
        abort_unless($this->cadangan->ada($berkas), 404, 'Berkas cadangan tidak ditemukan.');

        $jalur = $this->cadangan->jalur($berkas);

        // Berkas cadangan ada di disk privat; inilah satu-satunya jalan keluarnya.
        return Storage::disk('local')->download($jalur, basename($berkas));
    }

    public function hapus(string $berkas): RedirectResponse
    {
        if (! $this->cadangan->hapus($berkas)) {
            return back()->with('galat', 'Berkas cadangan tidak ditemukan.');
        }

        return back()->with('sukses', 'Cadangan '.$berkas.' dihapus.');
    }
}
