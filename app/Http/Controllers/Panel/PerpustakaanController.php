<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Katalog buku & eksemplarnya.
 *
 * Menghapus buku yang eksemplarnya sedang dipinjam tidak diizinkan — buku yang
 * tidak lagi relevan cukup dinonaktifkan agar riwayat peminjaman tetap terbaca.
 */
class PerpustakaanController extends Controller
{
    public function index(Request $request): Response
    {
        $cari = trim($request->string('cari')->toString());

        $daftar = Book::query()
            ->withCount('eksemplar')
            ->when($cari !== '', fn ($q) => $q->cari($cari))
            ->orderBy('judul->id')
            ->get()
            ->map(fn (Book $buku): array => [
                'id' => $buku->id,
                'judul' => $buku->getTranslations('judul'),
                'slug' => $buku->getTranslation('slug', 'id', false),
                'sinopsis' => $buku->getTranslations('sinopsis'),
                'penulis' => $buku->penulis,
                'penerbit' => $buku->penerbit,
                'tahun_terbit' => $buku->tahun_terbit,
                'isbn' => $buku->isbn,
                'ddc' => $buku->ddc,
                'kategori' => $buku->kategori,
                'bahasa' => $buku->bahasa,
                'jumlah_halaman' => $buku->jumlah_halaman,
                'cover_media_id' => $buku->cover_media_id,
                'rak' => $buku->rak,
                'is_public' => $buku->is_public,
                'aktif' => $buku->aktif,
                'total_eksemplar' => $buku->eksemplar_count,
                'tersedia' => $buku->eksemplarTersedia(),
                'eksemplar' => $buku->eksemplar()->orderBy('id')->get()
                    ->map(fn (BookCopy $salinan): array => [
                        'id' => $salinan->id,
                        'kode_eksemplar' => $salinan->kode_eksemplar,
                        'status' => $salinan->status,
                        'label_status' => $salinan->labelStatus(),
                        'kondisi' => $salinan->kondisi,
                        'rak' => $salinan->rak,
                        'nilai' => $salinan->nilai,
                        'catatan' => $salinan->catatan,
                        'sedang_dipinjam' => $salinan->pinjamanAktif() !== null,
                    ]),
            ]);

        return Inertia::render('Panel/Perpustakaan/Index', [
            'daftar' => $daftar,
            'cari' => $cari,
            'statusEksemplar' => BookCopy::STATUS,
            'kondisi' => BookCopy::STATUS,
            'pilihanCover' => Media::query()
                ->where('mime_type', 'like', 'image/%')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'nama' => $media->name ?: $media->file_name,
                    'url' => $media->hasGeneratedConversion('kecil') ? $media->getUrl('kecil') : $media->getUrl(),
                ]),
        ]);
    }

    /* ------------------------------- Buku ------------------------------- */

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $buku = new Book;
        $this->isi($buku, $data);
        $buku->setTranslations('slug', ['id' => Book::slugUnik($data['judul']['id'])]);
        $buku->save();

        return back()->with('sukses', 'Buku '.$buku->judulTeks().' ditambahkan ke katalog.');
    }

    public function perbarui(Request $request, Book $buku): RedirectResponse
    {
        $data = $this->validasi($request);

        $this->isi($buku, $data);

        if (blank($buku->getTranslation('slug', 'id', false))) {
            $buku->setTranslations('slug', ['id' => Book::slugUnik($data['judul']['id'], $buku->id)]);
        }

        $buku->save();

        return back()->with('sukses', 'Data buku diperbarui.');
    }

    public function hapus(Book $buku): RedirectResponse
    {
        $sedangDipinjam = $buku->eksemplar()
            ->where('status', BookCopy::STATUS_DIPINJAM)
            ->count();

        if ($sedangDipinjam > 0) {
            return back()->with('galat', 'Masih ada '.$sedangDipinjam.' eksemplar yang sedang dipinjam. Nonaktifkan saja judul ini.');
        }

        if ($buku->peminjaman()->exists() || $buku->eksemplar()->whereHas('peminjaman')->exists()) {
            return back()->with('galat', 'Judul ini punya riwayat peminjaman. Nonaktifkan saja agar riwayatnya tetap utuh.');
        }

        $judul = $buku->judulTeks();
        $buku->delete();

        return back()->with('sukses', 'Buku '.$judul.' dihapus.');
    }

    /* ----------------------------- Eksemplar ----------------------------- */

    public function simpanEksemplar(Request $request, Book $buku): RedirectResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1', 'max:50'],
            'rak' => ['nullable', 'string', 'max:60'],
            'nilai' => ['nullable', 'integer', 'min:0'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], ['jumlah.min' => 'Tambahkan minimal satu eksemplar.']);

        $adaSebelumnya = $buku->eksemplar()->count();

        for ($i = 1; $i <= (int) $data['jumlah']; $i++) {
            $salinan = new BookCopy;
            $salinan->book_id = $buku->id;
            // Kode dibuat berurutan dari jumlah eksemplar yang pernah ada,
            // supaya kode lama tidak pernah dipakai ulang.
            $salinan->kode_eksemplar = 'BK-'.str_pad((string) $buku->id, 3, '0', STR_PAD_LEFT)
                .'-'.($adaSebelumnya + $i);
            $salinan->kondisi = 'baik';
            $salinan->status = BookCopy::STATUS_TERSEDIA;
            $salinan->rak = $data['rak'] ?? $buku->rak;
            $salinan->nilai = (int) ($data['nilai'] ?? 0);
            $salinan->tanggal_perolehan = $data['tanggal_perolehan'] ?? null;
            $salinan->catatan = $data['catatan'] ?? null;
            $salinan->save();
        }

        return back()->with('sukses', $data['jumlah'].' eksemplar ditambahkan.');
    }

    public function perbaruiEksemplar(Request $request, BookCopy $eksemplar): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(BookCopy::STATUS))],
            'rak' => ['nullable', 'string', 'max:60'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        // Eksemplar yang sedang dipinjam tidak boleh diubah statusnya dari sini —
        // statusnya ditentukan oleh proses pengembalian.
        if ($eksemplar->pinjamanAktif() && $data['status'] !== BookCopy::STATUS_DIPINJAM) {
            return back()->with('galat', 'Eksemplar ini sedang dipinjam. Selesaikan pengembaliannya lebih dulu lewat halaman Peminjaman.');
        }

        $eksemplar->status = $data['status'];
        $eksemplar->rak = $data['rak'] ?? null;
        $eksemplar->catatan = $data['catatan'] ?? null;
        $eksemplar->save();

        return back()->with('sukses', 'Eksemplar '.$eksemplar->kode_eksemplar.' diperbarui.');
    }

    public function hapusEksemplar(BookCopy $eksemplar): RedirectResponse
    {
        if ($eksemplar->peminjaman()->exists()) {
            return back()->with('galat', 'Eksemplar ini punya riwayat peminjaman. Tandai hilang atau dalam perbaikan saja.');
        }

        $kode = $eksemplar->kode_eksemplar;
        $eksemplar->delete();

        return back()->with('sukses', 'Eksemplar '.$kode.' dihapus.');
    }

    /* ------------------------------ Bantuan ------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:190'],
            'judul.en' => ['nullable', 'string', 'max:190'],
            'sinopsis' => ['nullable', 'array'],
            'sinopsis.id' => ['nullable', 'string', 'max:3000'],
            'sinopsis.en' => ['nullable', 'string', 'max:3000'],
            'penulis' => ['nullable', 'string', 'max:190'],
            'penerbit' => ['nullable', 'string', 'max:190'],
            'tahun_terbit' => ['nullable', 'integer', 'min:1500', 'max:'.(int) now()->addYear()->format('Y')],
            'isbn' => ['nullable', 'string', 'max:32'],
            'ddc' => ['nullable', 'string', 'max:32'],
            'kategori' => ['nullable', 'string', 'max:120'],
            'bahasa' => ['nullable', 'string', 'max:12'],
            'jumlah_halaman' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'rak' => ['nullable', 'string', 'max:60'],
            'is_public' => ['boolean'],
            'aktif' => ['boolean'],
        ], ['judul.id.required' => 'Judul buku (Indonesia) wajib diisi.']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isi(Book $buku, array $data): void
    {
        $buku->penulis = $data['penulis'] ?? null;
        $buku->penerbit = $data['penerbit'] ?? null;
        $buku->tahun_terbit = $data['tahun_terbit'] ?? null;
        $buku->isbn = $data['isbn'] ?? null;
        $buku->ddc = $data['ddc'] ?? null;
        $buku->kategori = $data['kategori'] ?? null;
        $buku->bahasa = $data['bahasa'] ?? 'id';
        $buku->jumlah_halaman = $data['jumlah_halaman'] ?? null;
        $buku->cover_media_id = $data['cover_media_id'] ?? null;
        $buku->rak = $data['rak'] ?? null;
        $buku->is_public = (bool) ($data['is_public'] ?? true);
        $buku->aktif = (bool) ($data['aktif'] ?? true);

        $buku->setTranslations('judul', $this->bersihkan($data['judul']));
        $buku->setTranslations('sinopsis', $this->bersihkan($data['sinopsis'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, string>
     */
    private function bersihkan(array $nilai): array
    {
        return array_filter($nilai, fn ($isi) => is_string($isi) && trim($isi) !== '');
    }
}
