<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookReservation;
use App\Models\InventoryItem;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\Member;
use App\Services\Peminjaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan peminjaman oleh Sekretaris.
 *
 * TERMASUK PEMINJAMAN EKSTERNAL: pihak luar boleh meminjam, tetapi WAJIB ada
 * anggota yang bertanggung jawab. Syarat itu ditegakkan di App\Services\Peminjaman,
 * bukan hanya di formulir.
 */
class PeminjamanController extends Controller
{
    public function __construct(
        private readonly Peminjaman $peminjaman,
    ) {}

    public function index(Request $request): Response
    {
        $saring = [
            'status' => $request->string('status')->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $daftar = Loan::query()
            ->with([
                'eksemplar.buku:id,judul',
                'aset:id,kode,nama,satuan',
                'anggota:id,nama_lengkap,nomor_anggota',
                'penanggungJawab:id,nama_lengkap',
                'perpanjangan',
            ])
            ->when($saring['status'] !== '', fn ($q) => $q->where('status', $saring['status']))
            ->when($saring['jenis'] !== '', fn ($q) => $q->where('jenis', $saring['jenis']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('kode_pinjam', 'like', "%{$saring['cari']}%")
                    ->orWhere('peminjam_nama', 'like', "%{$saring['cari']}%"),
            ))
            ->orderByRaw("CASE status
                WHEN 'diajukan' THEN 1
                WHEN 'disetujui' THEN 2
                WHEN 'dipinjam' THEN 3
                WHEN 'dikembalikan' THEN 4
                ELSE 5 END")
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $item): array => [
                'id' => $item->id,
                'kode_pinjam' => $item->kode_pinjam,
                'barang' => $item->apaYangDipinjam(),
                'jenis' => $item->jenis,
                'label_jenis' => $item->labelJenis(),
                'peminjam' => $item->namaPeminjam(),
                'peminjam_kontak' => $item->peminjam_kontak,
                'peminjam_instansi' => $item->peminjam_instansi,
                'penanggung_jawab' => $item->penanggungJawab?->nama_lengkap,
                'jumlah' => $item->jumlah,
                'status' => $item->status,
                'label_status' => $item->labelStatus(),
                'jatuh_tempo' => $item->jatuh_tempo?->format('Y-m-d'),
                'sedang_dipinjam' => $item->sedangDipinjam(),
                'terlambat' => $item->terlambat(),
                'hari_terlambat' => $item->hariTerlambat(),
                'sisa_hari' => $item->sisaHari(),
                'perpanjangan_ke' => $item->perpanjangan_ke,
                'perpanjangan_diajukan' => $item->perpanjangan->firstWhere('status', LoanExtension::STATUS_DIAJUKAN)?->id,
                'perpanjangan_alasan' => $item->perpanjangan->firstWhere('status', LoanExtension::STATUS_DIAJUKAN)?->alasan,
                'kondisi_keluar' => $item->kondisi_keluar,
                'kondisi_masuk' => $item->kondisi_masuk,
                'catatan_peminjam' => $item->catatan_peminjam,
                'catatan_petugas' => $item->catatan_petugas,
                'diajukan_pada' => $item->diajukan_pada?->translatedFormat('d M Y, H:i'),
            ]);

        return Inertia::render('Panel/Peminjaman/Index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'status' => Loan::STATUS,
            // Dua daftar terpisah: saat diserahkan, "hilang" belum mungkin terjadi.
            // Sebelumnya di sini tertulis BookCopy::STATUS — itu keliru, karena
            // isinya status eksemplar (Tersedia/Sedang Dipinjam), bukan kondisi
            // barang. Pilihan itu selalu ditolak validasi.
            'kondisiKeluar' => Loan::KONDISI_KELUAR,
            'kondisiMasuk' => Loan::KONDISI_MASUK,
            'jenis' => ['internal' => 'Internal (Anggota)', 'eksternal' => 'Eksternal (Luar)'],
            'ringkasan' => [
                'diajukan' => Loan::query()->where('status', Loan::STATUS_DIAJUKAN)->count(),
                'dipinjam' => Loan::query()->where('status', Loan::STATUS_DIPINJAM)->count(),
                'terlambat' => Loan::query()->terlambat()->count(),
                'perpanjangan' => LoanExtension::query()->where('status', LoanExtension::STATUS_DIAJUKAN)->count(),
                'antrian_siap' => BookReservation::query()->where('status', BookReservation::STATUS_SIAP)->count(),
            ],
            'antrian' => BookReservation::query()
                ->with(['buku:id,judul', 'anggota:id,nama_lengkap'])
                ->whereIn('status', BookReservation::STATUS_AKTIF)
                ->orderBy('book_id')
                ->orderBy('posisi')
                ->get()
                ->map(fn (BookReservation $r): array => [
                    'id' => $r->id,
                    'judul' => $r->buku?->judulTeks(),
                    'anggota' => $r->anggota?->nama_lengkap,
                    'status' => $r->status,
                    'label_status' => $r->labelStatus(),
                    'posisi' => $r->posisi,
                    'kedaluwarsa_pada' => $r->kedaluwarsa_pada?->translatedFormat('d M Y, H:i'),
                ]),
            'pilihanBuku' => Book::query()->aktif()
                ->whereHas('eksemplar', fn ($q) => $q->where('status', BookCopy::STATUS_TERSEDIA))
                ->orderBy('judul->id')
                ->get()
                ->map(fn (Book $b): array => ['id' => $b->id, 'label' => $b->judulTeks().' — '.$b->eksemplarTersedia().' eksemplar tersedia']),
            'pilihanAset' => InventoryItem::query()->aktif()
                ->where('jumlah', '>', 0)
                ->orderBy('nama->id')
                ->get()
                ->map(fn (InventoryItem $a): array => ['id' => $a->id, 'label' => $a->namaTeks().' — '.$a->jumlahTersedia().' '.$a->satuan.' tersedia']),
            'pilihanAnggota' => Member::query()
                ->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])
                ->orderBy('nama_lengkap')
                ->limit(500)
                ->get(['id', 'nama_lengkap', 'nomor_anggota'])
                ->map(fn (Member $m): array => ['id' => $m->id, 'label' => $m->nama_lengkap.($m->nomor_anggota ? ' — '.$m->nomor_anggota : '')]),
        ]);
    }

    /**
     * Catat peminjaman eksternal atas nama pihak luar.
     */
    public function simpanEksternal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'jumlah' => ['nullable', 'integer', 'min:1', 'max:100'],
            'peminjam_nama' => ['required', 'string', 'max:160'],
            'peminjam_kontak' => ['nullable', 'string', 'max:120'],
            'peminjam_instansi' => ['nullable', 'string', 'max:190'],
            'penanggung_jawab_id' => ['required', 'integer', 'exists:members,id'],
            'catatan_peminjam' => ['nullable', 'string', 'max:500'],
        ], [
            'penanggung_jawab_id.required' => 'Peminjaman eksternal wajib memiliki penanggung jawab internal dari anggota rayon.',
            'peminjam_nama.required' => 'Nama peminjam wajib diisi.',
        ]);

        if (blank($data['book_id'] ?? null) && blank($data['inventory_item_id'] ?? null)) {
            return back()->with('galat', 'Pilih buku atau aset yang akan dipinjam.');
        }

        try {
            $pinjaman = filled($data['book_id'] ?? null)
                ? $this->peminjaman->ajukanBuku(Book::query()->findOrFail($data['book_id']), $data, null, $request->user())
                : $this->peminjaman->ajukanAset(InventoryItem::query()->findOrFail($data['inventory_item_id']), $data, null, $request->user());
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', 'Peminjaman eksternal '.$pinjaman->kode_pinjam.' dicatat dengan penanggung jawab internal.');
    }

    public function setujui(Request $request, Loan $pinjaman): RedirectResponse
    {
        $data = $request->validate(['catatan_petugas' => ['nullable', 'string', 'max:1000']]);

        return $this->jalankan(
            fn () => $this->peminjaman->setujui($pinjaman, $request->user(), $data['catatan_petugas'] ?? null),
            'Peminjaman '.$pinjaman->kode_pinjam.' disetujui. Jatuh temponya sudah ditetapkan.',
        );
    }

    public function tolak(Request $request, Loan $pinjaman): RedirectResponse
    {
        $data = $request->validate(
            ['catatan_petugas' => ['required', 'string', 'max:1000']],
            ['catatan_petugas.required' => 'Alasan penolakan wajib diisi agar peminjam tahu sebabnya.'],
        );

        return $this->jalankan(
            fn () => $this->peminjaman->tolak($pinjaman, $request->user(), $data['catatan_petugas']),
            'Peminjaman '.$pinjaman->kode_pinjam.' ditolak.',
        );
    }

    public function serahkan(Request $request, Loan $pinjaman): RedirectResponse
    {
        $data = $request->validate([
            'kondisi_keluar' => ['required', Rule::in(array_keys(Loan::KONDISI_KELUAR))],
            'catatan_petugas' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->jalankan(
            fn () => $this->peminjaman->serahkan($pinjaman, $request->user(), $data['kondisi_keluar'], $data['catatan_petugas'] ?? null),
            'Barang diserahkan. Jatuh tempo '.$pinjaman->fresh()->jatuh_tempo?->translatedFormat('d F Y').'.',
        );
    }

    public function kembalikan(Request $request, Loan $pinjaman): RedirectResponse
    {
        $data = $request->validate([
            'kondisi_masuk' => ['required', Rule::in(array_keys(Loan::KONDISI_MASUK))],
            'catatan_petugas' => ['nullable', 'string', 'max:1000'],
        ]);

        // Hilang bukan pengembalian — jalurnya beda karena eksemplarnya tidak kembali.
        if ($data['kondisi_masuk'] === Loan::KONDISI_HILANG) {
            $data['catatan_petugas'] = $data['catatan_petugas'] ?? 'Ditandai hilang saat pengembalian.';

            return $this->jalankan(
                fn () => $this->peminjaman->tandaiHilang($pinjaman, $request->user(), $data['catatan_petugas']),
                'Peminjaman ditandai hilang. Antrian tetap berjalan.',
            );
        }

        return $this->jalankan(
            fn () => $this->peminjaman->kembalikan($pinjaman, $request->user(), $data['kondisi_masuk'], $data['catatan_petugas'] ?? null),
            'Pengembalian diterima. Bila ada antrian, giliran berikutnya otomatis dinaikkan.',
        );
    }

    public function batalkan(Request $request, Loan $pinjaman): RedirectResponse
    {
        $data = $request->validate(['catatan_petugas' => ['nullable', 'string', 'max:1000']]);

        return $this->jalankan(
            fn () => $this->peminjaman->batalkan($pinjaman, $data['catatan_petugas'] ?? 'Dibatalkan oleh petugas.'),
            'Peminjaman '.$pinjaman->kode_pinjam.' dibatalkan.',
        );
    }

    public function setujuiPerpanjangan(Request $request, LoanExtension $perpanjangan): RedirectResponse
    {
        $data = $request->validate(['catatan_petugas' => ['nullable', 'string', 'max:1000']]);

        return $this->jalankan(
            fn () => $this->peminjaman->setujuiPerpanjangan($perpanjangan, $request->user(), $data['catatan_petugas'] ?? null),
            'Perpanjangan disetujui. Jatuh tempo baru sudah ditetapkan.',
        );
    }

    public function tolakPerpanjangan(Request $request, LoanExtension $perpanjangan): RedirectResponse
    {
        $data = $request->validate(
            ['catatan_petugas' => ['required', 'string', 'max:1000']],
            ['catatan_petugas.required' => 'Alasan penolakan perpanjangan wajib diisi.'],
        );

        return $this->jalankan(
            fn () => $this->peminjaman->tolakPerpanjangan($perpanjangan, $request->user(), $data['catatan_petugas']),
            'Perpanjangan ditolak.',
        );
    }

    /**
     * Jalankan aksi layanan dan ubah galat validasinya menjadi pesan yang terbaca.
     */
    private function jalankan(callable $aksi, string $pesanSukses): RedirectResponse
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', $pesanSukses);
    }
}
