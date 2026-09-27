<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookReservation;
use App\Models\Loan;
use App\Services\Peminjaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pustaka untuk kader & alumni — meminjam dari dashboard masing-masing.
 *
 * Tidak memakai izin Spatie (sama seperti /karya): yang menentukan adalah
 * keanggotaan yang sudah diverifikasi. Pinjaman SELALU tercatat atas nama
 * anggota milik pengguna yang sedang masuk — id anggota dari formulir diabaikan,
 * supaya tidak ada yang dapat meminjam atas nama orang lain.
 */
class PustakaController extends Controller
{
    public function __construct(
        private readonly Peminjaman $peminjaman,
    ) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        abort_if($anggota === null, 403, 'Halaman ini untuk anggota rayon.');

        $pinjaman = Loan::query()
            ->untukAnggota($anggota->id)
            ->with(['eksemplar.buku', 'aset', 'perpanjangan'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $item): array => [
                'id' => $item->id,
                'kode_pinjam' => $item->kode_pinjam,
                'barang' => $item->apaYangDipinjam(),
                'status' => $item->status,
                'label_status' => $item->labelStatus(),
                'jatuh_tempo' => $item->jatuh_tempo?->format('Y-m-d'),
                'sedang_dipinjam' => $item->sedangDipinjam(),
                'terlambat' => $item->terlambat(),
                'hari_terlambat' => $item->hariTerlambat(),
                'sisa_hari' => $item->sisaHari(),
                'boleh_diperpanjang' => $item->bolehDiperpanjang(),
                'perpanjangan_ke' => $item->perpanjangan_ke,
                'ada_pengajuan_perpanjangan' => $item->perpanjangan->contains(
                    fn ($p): bool => $p->status === \App\Models\LoanExtension::STATUS_DIAJUKAN,
                ),
                'catatan_petugas' => $item->catatan_petugas,
            ]);

        return Inertia::render('Anggota/Pustaka', [
            'pinjaman' => $pinjaman,
            'sedangDipinjam' => $pinjaman->where('sedang_dipinjam', true)->values(),
            'riwayat' => $pinjaman->where('sedang_dipinjam', false)->values(),
            'antrian' => BookReservation::query()
                ->where('member_id', $anggota->id)
                ->whereIn('status', BookReservation::STATUS_AKTIF)
                ->with('buku')
                ->orderBy('posisi')
                ->get()
                ->map(fn (BookReservation $r): array => [
                    'id' => $r->id,
                    'judul' => $r->buku?->judulTeks(),
                    'status' => $r->status,
                    'label_status' => $r->labelStatus(),
                    'posisi' => $r->posisi,
                    'kedaluwarsa_pada' => $r->kedaluwarsa_pada?->translatedFormat('d F Y, H:i'),
                    'sisa_jam' => $r->sisaJam(),
                ]),
            'bisaDipinjam' => Book::query()
                ->publik()
                ->whereHas('eksemplar', fn ($q) => $q->where('status', \App\Models\BookCopy::STATUS_TERSEDIA))
                ->orderBy('judul->id')
                ->limit(50)
                ->get()
                ->map(fn (Book $buku): array => [
                    'id' => $buku->id,
                    'judul' => $buku->judulTeks(),
                    'penulis' => $buku->penulis,
                    'tersedia' => $buku->eksemplarBebas(),
                    // Judul yang eksemplarnya sedang ditahan untuk anggota ini
                    // tetap ditawarkan walau hitungan bebasnya nol.
                    'untukku' => $buku->reservasiSiapUntuk($anggota) !== null,
                ]),
            'batasPerpanjangan' => (int) \App\Support\Pengaturan::angka('pinjaman_perpanjangan_maks', 1),
            'durasiHari' => Loan::durasiHariDefault(),
        ]);
    }

    public function pinjam(Request $request): RedirectResponse
    {
        $anggota = $request->user()->member;

        abort_if($anggota === null, 403, 'Halaman ini untuk anggota rayon.');

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'catatan_peminjam' => ['nullable', 'string', 'max:500'],
        ]);

        $buku = Book::query()->publik()->findOrFail($data['book_id']);

        try {
            $pinjaman = $this->peminjaman->ajukanBuku($buku, $data, $anggota);
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', 'Pengajuan peminjaman terkirim dengan kode '.$pinjaman->kode_pinjam.'. Tunggu persetujuan Sekretaris.');
    }

    public function antri(Request $request): RedirectResponse
    {
        $anggota = $request->user()->member;

        abort_if($anggota === null, 403, 'Halaman ini untuk anggota rayon.');

        $data = $request->validate(['book_id' => ['required', 'integer', 'exists:books,id']]);
        $buku = Book::query()->publik()->findOrFail($data['book_id']);

        try {
            $reservasi = $this->peminjaman->antri($buku, $anggota);
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', 'Kamu masuk daftar tunggu '.$buku->judulTeks().' pada urutan '.$reservasi->posisi.'. Kami kabari lewat email saat tersedia.');
    }

    public function perpanjang(Request $request, Loan $pinjaman): RedirectResponse
    {
        $anggota = $request->user()->member;

        // Hanya pinjaman miliknya sendiri.
        abort_unless($anggota !== null && $pinjaman->member_id === $anggota->id, 403);

        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:500']]);

        try {
            $this->peminjaman->ajukanPerpanjangan($pinjaman, $data['alasan'] ?? null);
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', 'Pengajuan perpanjangan terkirim. Tunggu persetujuan Sekretaris.');
    }

    public function batalkan(Request $request, Loan $pinjaman): RedirectResponse
    {
        $anggota = $request->user()->member;

        abort_unless($anggota !== null && $pinjaman->member_id === $anggota->id, 403);

        if ($pinjaman->status !== Loan::STATUS_DIAJUKAN) {
            return back()->with('galat', 'Pengajuan yang sudah diproses tidak dapat dibatalkan sendiri. Hubungi Sekretaris.');
        }

        $this->peminjaman->batalkan($pinjaman, 'Dibatalkan oleh peminjam.');

        return back()->with('sukses', 'Pengajuan peminjaman dibatalkan.');
    }
}
