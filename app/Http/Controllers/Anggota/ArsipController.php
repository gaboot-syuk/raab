<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Arsip;
use App\Support\Audiens;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;

/**
 * Arsip dokumen di area anggota.
 *
 * Kader dan alumni memakai halaman yang sama; yang berbeda hanya dokumen mana
 * yang muncul, karena penyaringan audiensnya dijalankan di layanan. Alumni
 * memang masih berkepentingan membaca AD/ART dan hasil rapat.
 */
class ArsipController extends Controller
{
    public function __construct(private Arsip $arsip) {}

    public function index(Request $request): ResponsInertia
    {
        $pengguna = $request->user();
        $kategori = $request->string('kategori')->toString();

        $daftar = $this->arsip->untuk($pengguna, $kategori !== '' ? $kategori : null)
            ->map(fn (Document $d): array => [
                'id' => $d->id,
                'slug' => $d->slug,
                'judul' => $d->judulTeks(),
                'keterangan' => $d->keteranganTeks(),
                'label_kategori' => $d->labelKategori(),
                'nomor' => $d->nomor,
                'tanggal_teks' => $d->tanggal_dokumen?->translatedFormat('d F Y'),
                'audiens_teks' => $d->audiensTeks(),
                'nama_berkas' => $d->namaBerkas(),
                'ukuran' => $d->ukuranTeks(),
                'punya_berkas' => $d->punyaBerkas(),
                'tautan_unduh' => route('arsip.unduh', $d),
                'pengunggah' => $d->pengunggah?->name,
            ])->all();

        return Inertia::render('Anggota/Arsip', [
            'daftar' => $daftar,
            'kategori' => $kategori,
            'pilihanKategori' => Document::KATEGORI,
            'total' => count($daftar),
            'audiensSaya' => Audiens::dimiliki($pengguna),
            'catatan' => 'Dokumen di sini sudah disaring menurut hak aksesmu. Dokumen yang hanya dibuka untuk pengurus memang tidak muncul — bukan disembunyikan di tampilan, melainkan tidak pernah dikirim ke perambanmu.',
        ]);
    }
}
