<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Period;
use App\Services\Arsip;
use App\Support\Audiens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;
use Symfony\Component\HttpFoundation\Response as ResponsHttp;

/**
 * Panel arsip dokumen.
 *
 * `documents.view` untuk membaca daftar, `documents.manage` untuk mengunggah
 * dan mengubah. Bendahara ikut memegang `documents.view` — ia perlu membaca
 * AD/ART dan hasil rapat yang menyangkut anggaran, tanpa perlu bisa menambah
 * atau menghapus arsip.
 */
class ArsipController extends Controller
{
    public function __construct(private Arsip $arsip) {}

    public function index(Request $request): ResponsInertia
    {
        $kategori = $request->string('kategori')->toString();

        return Inertia::render('Panel/Arsip/Index', [
            'daftar' => $this->arsip->daftarPanel($kategori !== '' ? $kategori : null),
            'rekap' => $this->arsip->rekap(),
            'kategori' => $kategori,
            'pilihanKategori' => Document::KATEGORI,
            'pilihanAudiens' => Audiens::PILIHAN,
            'periode' => Period::query()->orderByDesc('mulai')->get()
                ->map(fn (Period $p): array => ['id' => $p->id, 'nama' => $p->nama])->all(),
            'maksKb' => Arsip::MAKS_KB,
            'catatan' => 'Audiens menentukan siapa yang boleh MENGUNDUH, bukan sekadar siapa yang melihat daftarnya — pemeriksaannya ada di jalur unduhan. Pengurus selalu bisa membaca semua dokumen supaya orang yang mengelola arsip dapat memeriksa sendiri apa yang dilihat kader. Berkas arsip disimpan di penyimpanan privat, jadi tidak bisa dibuka lewat alamat langsung.',
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        try {
            $dokumen = $this->arsip->simpan($data, $request->user(), $request->file('berkas'));
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first())->withInput();
        }

        return back()->with('sukses', 'Dokumen "'.$dokumen->judulTeks().'" ditambahkan ke arsip.');
    }

    public function perbarui(Request $request, Document $dokumen): RedirectResponse
    {
        $data = $this->validasi($request);

        try {
            $this->arsip->perbarui($dokumen, $data, $request->user(), $request->file('berkas'));
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first())->withInput();
        }

        return back()->with('sukses', 'Dokumen "'.$dokumen->judulTeks().'" diperbarui.');
    }

    public function hapus(Request $request, Document $dokumen): RedirectResponse
    {
        $judul = $dokumen->judulTeks();

        $this->arsip->hapus($dokumen, $request->user());

        return back()->with('sukses', 'Dokumen "'.$judul.'" dihapus. BERKASNYA sengaja tidak ikut dihapus dari pustaka media, sehingga salah hapus masih bisa dipulihkan.');
    }

    public function ekspor(): ResponsHttp
    {
        $bersih = fn (?string $teks): string => '"'.str_replace('"', '""', (string) $teks).'"';

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Judul;Kategori;Nomor;Tanggal;Audiens;Nama Berkas;Ukuran;Diunggah\n";

        foreach (Document::query()->terbaruDulu()->limit(2000)->get() as $d) {
            $isi .= implode(';', [
                $bersih($d->judulTeks()),
                $bersih($d->labelKategori()),
                $bersih($d->nomor),
                $bersih($d->tanggal_dokumen?->format('Y-m-d')),
                $bersih(implode(', ', $d->audiensTeks())),
                $bersih($d->namaBerkas()),
                $bersih($d->ukuranTeks()),
                $bersih($d->created_at?->format('Y-m-d H:i')),
            ])."\n";
        }

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="arsip-dokumen.csv"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:190'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'kategori' => ['required', Rule::in(array_keys(Document::KATEGORI))],
            'nomor' => ['nullable', 'string', 'max:64'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'akses' => ['required', 'array', 'min:1'],
            'akses.*' => [Rule::in(array_keys(Audiens::PILIHAN))],
            'tautan_luar' => ['nullable', 'url', 'max:500'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'berkas' => [
                'nullable',
                'file',
                'max:'.Arsip::MAKS_KB,
                // Jenis berkas diperiksa dari ISINYA (mimes), bukan dari
                // namanya — nama berkas mudah dipalsukan.
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp',
            ],
        ], [
            'akses.required' => 'Pilih minimal satu audiens.',
            'akses.min' => 'Pilih minimal satu audiens.',
            'berkas.max' => 'Ukuran berkas paling besar '.(Arsip::MAKS_KB / 1024).' MB.',
            'berkas.mimes' => 'Jenis berkas tidak diterima. Gunakan PDF, Word, Excel, atau gambar.',
        ]);
    }
}
