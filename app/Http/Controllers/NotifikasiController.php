<?php

namespace App\Http\Controllers;

use App\Services\Notifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;

/**
 * Pusat notifikasi dalam aplikasi.
 *
 * SATU pengendali untuk dua kerangka halaman: pengurus melihatnya di dalam
 * panel, kader dan alumni melihatnya di area anggota. Yang berbeda hanya
 * kerangkanya; notifikasinya sama, dan menyalin seluruh logika penerimaan ke
 * dua pengendali hanya akan membuat keduanya berbeda cepat atau lambat.
 *
 * SEMUA tindakan di sini bekerja pada notifikasi MILIK pengguna yang sedang
 * masuk. Kepemilikan diperiksa lewat relasi (`$pengguna->notifications()`),
 * bukan lewat id saja — kalau tidak, id notifikasi orang lain bisa ditandai
 * sudah dibaca dari sini.
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): ResponsInertia
    {
        $pengguna = $request->user();
        $kategori = $request->string('kategori')->toString();

        // Pengurus memakai kerangka panel, anggota memakai kerangka area anggota.
        // Dua-duanya menampilkan daftar yang sama.
        $komponen = method_exists($pengguna, 'roles') && $pengguna->roles->isNotEmpty()
            ? 'Panel/Notifikasi/Index'
            : 'Anggota/Notifikasi';

        return Inertia::render($komponen, [
            'daftar' => Notifikasi::daftar($pengguna, $kategori !== '' ? $kategori : null),
            'kategori' => $kategori,
            'pilihanKategori' => Notifikasi::KATEGORI,
            'jumlahPerKategori' => Notifikasi::jumlahPerKategori($pengguna),
            'belumDibaca' => Notifikasi::belumDibaca($pengguna),
            'preferensi' => Notifikasi::pilihanPreferensi($pengguna),
            'catatan' => 'Notifikasi di halaman ini selalu aktif dan tidak bisa dimatikan — yang bisa kamu atur hanya pengiriman lewat email. Kategori Keanggotaan sengaja tidak punya sakelar sama sekali: surat dari kategori itulah yang memberi tahu bahwa kamu sudah boleh masuk, dan orang yang mematikannya sebelum tahu apa pun tidak akan pernah tahu bahwa ia diterima.',
        ]);
    }

    public function baca(Request $request, string $notifikasi): RedirectResponse
    {
        $berhasil = Notifikasi::tandaiDibaca($request->user(), $notifikasi);

        return $berhasil
            ? back()->with('sukses', 'Notifikasi ditandai sudah dibaca.')
            : back()->with('galat', 'Notifikasi itu bukan milikmu, atau sudah tidak ada.');
    }

    public function bacaSemua(Request $request): RedirectResponse
    {
        $jumlah = Notifikasi::tandaiSemuaDibaca($request->user());

        return back()->with('sukses', $jumlah === 0
            ? 'Tidak ada notifikasi yang belum dibaca.'
            : $jumlah.' notifikasi ditandai sudah dibaca.');
    }

    /**
     * Simpan preferensi email.
     *
     * Kategori yang bertanda WAJIB diabaikan dari isian pengguna — nilainya
     * ditetapkan sekali di layanan, supaya aturannya tidak hidup di dua tempat.
     */
    public function preferensi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kategori' => ['array'],
            'kategori.*' => ['boolean'],
        ]);

        Notifikasi::simpanPreferensi($request->user(), $data['kategori'] ?? []);

        return back()->with('sukses', 'Preferensi email disimpan. Notifikasi dalam aplikasi tetap aktif seperti biasa.');
    }
}
