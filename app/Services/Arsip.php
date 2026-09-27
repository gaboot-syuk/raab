<?php

namespace App\Services;

use App\Models\Document;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Support\Audiens;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Arsip dokumen.
 *
 * TIGA JANJI:
 *
 * 1. HAK AKSES DIPERIKSA SAAT MENGUNDUH, bukan hanya saat menampilkan daftar.
 *    Daftar yang sudah disaring tetap bisa ditembus lewat alamat langsung bila
 *    pemeriksaannya hanya ada di daftar. Karena itu `bolehUnduh()` dipanggil
 *    di jalur unduhan, dan itulah yang diuji.
 *
 * 2. MENGHAPUS DOKUMEN TIDAK MENGHAPUS BERKASNYA. Dokumen dihapus lunak dan
 *    berkasnya tetap tinggal di pustaka media. Salah hapus arsip AD/ART tidak
 *    boleh berarti dokumen itu hilang selamanya.
 *
 * 3. SATU BERKAS, SATU HAK AKSES. Berkas tidak disalin ke tabel `documents`;
 *    yang disimpan hanya id-nya. Kalau disalin, akan ada dua salinan yang
 *    haknya bisa berbeda dan tidak ada yang tahu mana yang berlaku.
 */
class Arsip
{
    /** Ukuran maksimum berkas dokumen (KB). */
    public const MAKS_KB = 20480;

    /** Jenis berkas yang diterima. */
    public const MIME = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, User $petugas, ?UploadedFile $berkas = null): Document
    {
        $bersih = $this->bersihkan($data);

        $dokumen = new Document;
        $dokumen->slug = Document::slugUnik($bersih['judul']);
        $dokumen->kategori = $bersih['kategori'];
        $dokumen->nomor = $bersih['nomor'];
        $dokumen->tanggal_dokumen = $bersih['tanggal_dokumen'];
        $dokumen->akses = $bersih['akses'];
        $dokumen->tautan_luar = $bersih['tautan_luar'];
        $dokumen->period_id = $bersih['period_id'];
        $dokumen->diunggah_oleh = $petugas->id;

        // Kolom JSON NOT NULL harus terisi SEBELUM save().
        $dokumen->setTranslations('judul', ['id' => $bersih['judul']]);

        if ($bersih['keterangan'] !== null) {
            $dokumen->setTranslations('keterangan', ['id' => $bersih['keterangan']]);
        }

        $dokumen->save();

        if ($berkas !== null) {
            $dokumen->media_id = $this->simpanBerkas($berkas, $petugas);
            $dokumen->save();
        }

        activity()
            ->performedOn($dokumen)
            ->withProperties(['kategori' => $dokumen->kategori, 'akses' => $dokumen->akses])
            ->log('Dokumen arsip ditambahkan');

        return $dokumen;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function perbarui(Document $dokumen, array $data, User $petugas, ?UploadedFile $berkas = null): Document
    {
        $bersih = $this->bersihkan($data);

        if ($bersih['judul'] !== $dokumen->judulTeks()) {
            $dokumen->slug = Document::slugUnik($bersih['judul'], $dokumen->id);
        }

        $dokumen->kategori = $bersih['kategori'];
        $dokumen->nomor = $bersih['nomor'];
        $dokumen->tanggal_dokumen = $bersih['tanggal_dokumen'];
        $dokumen->akses = $bersih['akses'];
        $dokumen->tautan_luar = $bersih['tautan_luar'];
        $dokumen->period_id = $bersih['period_id'];
        $dokumen->setTranslations('judul', ['id' => $bersih['judul']]);
        $dokumen->setTranslations('keterangan', $bersih['keterangan'] === null ? [] : ['id' => $bersih['keterangan']]);

        if ($berkas !== null) {
            // Berkas LAMA sengaja tidak dihapus dari pustaka media: kalau ada
            // yang perlu dibandingkan dengan versi sebelumnya, salinannya masih
            // ada. Yang berubah hanya berkas mana yang ditunjuk dokumen ini.
            $dokumen->media_id = $this->simpanBerkas($berkas, $petugas);
        }

        $dokumen->save();

        activity()
            ->performedOn($dokumen)
            ->withProperties(['akses' => $dokumen->akses])
            ->log('Dokumen arsip diperbarui');

        return $dokumen;
    }

    /**
     * Hapus lunak. Berkasnya tetap di pustaka media.
     */
    public function hapus(Document $dokumen, User $petugas): void
    {
        DB::transaction(function () use ($dokumen, $petugas): void {
            activity()
                ->performedOn($dokumen)
                ->causedBy($petugas)
                ->withProperties(['berkas' => $dokumen->namaBerkas()])
                ->log('Dokumen arsip dihapus');

            $dokumen->delete();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Pembacaan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Dokumen yang boleh dilihat seorang penonton.
     *
     * Penyaringan audiens terjadi DI SINI, bukan di tampilan.
     *
     * @return Collection<int, Document>
     */
    public function untuk(?User $pengguna, ?string $kategori = null, int $batas = 100): Collection
    {
        return Document::query()
            ->when($kategori, fn ($q) => $q->kategori($kategori))
            ->terbaruDulu()
            ->limit($batas)
            ->get()
            ->filter(fn (Document $d) => $d->bolehDibacaOleh($pengguna))
            ->values();
    }

    /**
     * Dokumen yang boleh dibaca siapa saja — untuk halaman publik.
     *
     * @return Collection<int, Document>
     */
    public function untukPublik(?string $kategori = null, int $batas = 50): Collection
    {
        return Document::query()
            ->when($kategori, fn ($q) => $q->kategori($kategori))
            ->terbaruDulu()
            ->limit($batas)
            ->get()
            ->filter(fn (Document $d) => $d->terbukaUntukUmum())
            ->values();
    }

    /**
     * Boleh tidaknya seorang penonton mengunduh sebuah dokumen.
     *
     * Dipanggil di jalur unduhan. Tanpa pemeriksaan ini, menyaring daftar tidak
     * ada gunanya: alamat unduhan bisa ditebak dari slug.
     */
    public function bolehUnduh(Document $dokumen, ?User $pengguna): bool
    {
        return $dokumen->bolehDibacaOleh($pengguna);
    }

    /**
     * Daftar untuk panel: SEMUA dokumen beserta siapa saja yang boleh membaca.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarPanel(?string $kategori = null, int $batas = 200): array
    {
        return Document::query()
            ->with('periode')
            ->when($kategori, fn ($q) => $q->kategori($kategori))
            ->terbaruDulu()
            ->limit($batas)
            ->get()
            ->map(fn (Document $d): array => [
                'id' => $d->id,
                'slug' => $d->slug,
                'judul' => $d->judulTeks(),
                'keterangan' => $d->keteranganTeks(),
                'kategori' => $d->kategori,
                'label_kategori' => $d->labelKategori(),
                'nomor' => $d->nomor,
                'tanggal' => $d->tanggal_dokumen?->format('Y-m-d'),
                'tanggal_teks' => $d->tanggal_dokumen?->translatedFormat('d F Y'),
                'akses' => $d->akses ?? [],
                'audiens_teks' => $d->audiensTeks(),
                'periode' => $d->periode?->nama ?? null,
                'nama_berkas' => $d->namaBerkas(),
                'ukuran' => $d->ukuranTeks(),
                'punya_berkas' => $d->punyaBerkas(),
                'tautan_luar' => $d->tautan_luar,
                'diunggah' => $d->created_at?->translatedFormat('d F Y'),
                'pengunggah' => $d->pengunggah?->name,
            ])->all();
    }

    /**
     * @return array{total: int, publik: int, internal: int, tanpa_berkas: int, kategori: array<string, int>}
     */
    public function rekap(): array
    {
        $semua = Document::query()->get();

        return [
            'total' => $semua->count(),
            'publik' => $semua->filter(fn (Document $d) => $d->terbukaUntukUmum())->count(),
            'internal' => $semua->reject(fn (Document $d) => $d->terbukaUntukUmum())->count(),
            'tanpa_berkas' => $semua->reject(fn (Document $d) => $d->punyaBerkas())->count(),
            'kategori' => $semua->groupBy('kategori')->map->count()->all(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Pembersihan & pemeriksaan                                           */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function bersihkan(array $data): array
    {
        $judul = trim((string) ($data['judul'] ?? ''));

        if ($judul === '') {
            throw ValidationException::withMessages(['judul' => 'Judul dokumen wajib diisi.']);
        }

        $kategori = (string) ($data['kategori'] ?? '');

        if (! array_key_exists($kategori, Document::KATEGORI)) {
            throw ValidationException::withMessages(['kategori' => 'Kategori dokumen tidak dikenal.']);
        }

        $akses = Audiens::bersihkan((array) ($data['akses'] ?? []));

        if ($akses === []) {
            throw ValidationException::withMessages([
                'akses' => 'Pilih minimal satu audiens — dokumen tanpa audiens tidak bisa dibuka siapa pun, termasuk yang mengunggahnya.',
            ]);
        }

        $keterangan = trim((string) ($data['keterangan'] ?? ''));

        return [
            'judul' => $judul,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'kategori' => $kategori,
            'nomor' => ($nomor = trim((string) ($data['nomor'] ?? ''))) !== '' ? $nomor : null,
            'tanggal_dokumen' => ($data['tanggal_dokumen'] ?? null) ?: null,
            'akses' => $akses,
            'tautan_luar' => ($tautan = trim((string) ($data['tautan_luar'] ?? ''))) !== '' ? $tautan : null,
            'period_id' => $data['period_id'] ?? null,
        ];
    }

    /**
     * Simpan berkas ke pustaka media, kembalikan id-nya.
     *
     * DISKNYA PRIVAT (`local`), bukan `public`. Kalau berkasnya ditaruh di disk
     * publik, kendali akses per audiens tidak ada gunanya: siapa pun yang
     * menebak alamat `/storage/{id}/{nama-berkas}` bisa mengunduhnya tanpa
     * melewati pemeriksaan apa pun.
     */
    private function simpanBerkas(UploadedFile $berkas, User $petugas): int
    {
        $media = MediaLibrary::induk()
            ->addMedia($berkas)
            ->usingName($berkas->getClientOriginalName())
            ->toMediaCollection('arsip', 'local');

        activity()
            ->performedOn(MediaLibrary::induk())
            ->causedBy($petugas)
            ->withProperties(['berkas' => $media->file_name, 'ukuran' => $media->size])
            ->log('Berkas arsip diunggah');

        return (int) $media->id;
    }
}
