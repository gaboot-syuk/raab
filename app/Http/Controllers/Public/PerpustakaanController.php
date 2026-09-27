<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\Member;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Katalog perpustakaan publik.
 *
 * Ketersediaan dihitung dari eksemplar, bukan dari judul — satu judul bisa
 * punya beberapa salinan dan sebagian sedang dipinjam.
 */
class PerpustakaanController extends Controller
{
    public function index(Request $request): View
    {
        $saring = [
            'cari' => trim($request->string('cari')->toString()),
            'kategori' => $request->string('kategori')->toString(),
            'tersedia' => $request->string('tersedia')->toString(),
        ];

        $daftar = Book::query()
            ->publik()
            ->withCount('eksemplar')
            ->when($saring['cari'] !== '', fn ($q) => $q->cari($saring['cari']))
            ->when($saring['kategori'] !== '', fn ($q) => $q->kategori($saring['kategori']))
            // "Hanya yang tersedia" harus memakai ukuran yang sama dengan yang
            // ditampilkan pada kartu, bukan sekadar ada eksemplar di rak.
            ->when($saring['tersedia'] === '1', fn ($q) => $q->punyaEksemplarBebas())
            ->orderBy('judul->id')
            ->paginate(18)
            ->withQueryString();

        return view('public.perpustakaan', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'saring' => $saring,
            'daftarKategori' => Book::query()->publik()
                ->whereNotNull('kategori')
                ->distinct()
                ->orderBy('kategori')
                ->pluck('kategori'),
            'jumlahTersedia' => Book::query()->publik()->punyaEksemplarBebas()->count(),
        ]);
    }

    public function detail(string $slug): View
    {
        $buku = Book::query()
            ->publik()
            ->where('slug->id', $slug)
            ->with('eksemplar')
            ->firstOrFail();

        $anggota = null;
        $antrian = null;

        if ($pengguna = auth()->user()) {
            $anggota = Member::query()->where('user_id', $pengguna->id)->first();

            if ($anggota) {
                $antrian = BookReservation::query()
                    ->where('book_id', $buku->id)
                    ->where('member_id', $anggota->id)
                    ->whereIn('status', BookReservation::STATUS_AKTIF)
                    ->first();
            }
        }

        return view('public.buku-detail', [
            'situs' => Pengaturan::semua(),
            'buku' => $buku,
            'anggota' => $anggota,
            'antrian' => $antrian,
            'jumlahAntrian' => BookReservation::query()
                ->where('book_id', $buku->id)
                ->whereIn('status', BookReservation::STATUS_AKTIF)
                ->count(),
            'bukuLain' => Book::query()->publik()
                ->whereKeyNot($buku->id)
                ->when($buku->kategori, fn ($q) => $q->where('kategori', $buku->kategori))
                ->limit(4)
                ->get(),
        ]);
    }
}
