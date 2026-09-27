<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\OrganisationUnit;
use App\Models\UnitAgenda;
use App\Support\PustakaMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Galeri foto dan agenda publik unit.
 *
 * Dikelola Konten Manager (izin `galleries.manage` / `unit-agendas.manage`).
 * Album dapat menempel pada unit tertentu atau berdiri sendiri sebagai galeri
 * rayon — mis. dokumentasi Mapaba yang bukan milik satu biro pun.
 */
class GaleriController extends Controller
{
    public function index(Request $request): Response
    {
        $unitId = $request->integer('unit');

        $album = Gallery::query()
            ->with('unit:id,nama,jenis')
            ->withCount('item')
            ->when($unitId > 0, fn ($q) => $q->where('unit_id', $unitId))
            ->urut()
            ->get()
            ->map(fn (Gallery $galeri): array => [
                'id' => $galeri->id,
                'judul' => $galeri->getTranslations('judul'),
                'slug' => $galeri->getTranslation('slug', 'id', false),
                'deskripsi' => $galeri->getTranslations('deskripsi'),
                'unit_id' => $galeri->unit_id,
                'unit' => $galeri->unit?->nama,
                'tanggal' => $galeri->tanggal?->format('Y-m-d'),
                'lokasi' => $galeri->lokasi,
                'publik' => $galeri->publik,
                'urutan' => $galeri->urutan,
                'jumlah_item' => $galeri->item_count,
                'tautan_publik' => $galeri->publik ? '/galeri/'.$galeri->getTranslation('slug', 'id', false) : null,
            ]);

        $agendaMentah = UnitAgenda::query()
            ->with('unit:id,nama')
            ->when($unitId > 0, fn ($q) => $q->where('unit_id', $unitId))
            ->orderByDesc('mulai')
            ->limit(60)
            ->get();

        // Satu kueri untuk SEMUA gambar agenda, bukan satu kueri per agenda.
        $gambarAgenda = PustakaMedia::peta($agendaMentah->pluck('gambar_media_id'));

        $agenda = $agendaMentah->map(fn (UnitAgenda $item): array => [
            'id' => $item->id,
            'judul' => $item->getTranslations('judul'),
            'unit_id' => $item->unit_id,
            'unit' => $item->unit?->nama,
            'deskripsi' => $item->getTranslations('deskripsi'),
            'mulai' => $item->mulai?->format('Y-m-d\TH:i'),
            'selesai' => $item->selesai?->format('Y-m-d\TH:i'),
            'lokasi' => $item->lokasi,
            'publik' => $item->publik,
            'mendatang' => $item->mulai?->isFuture() ?? false,
            'gambar_media_id' => $item->gambar_media_id,
            'gambar' => $item->gambar_media_id ? ($gambarAgenda[$item->gambar_media_id] ?? null) : null,
        ]);

        return Inertia::render('Panel/Organisasi/Galeri', [
            'album' => $album,
            'agenda' => $agenda,
            'pilihanGambar' => PustakaMedia::pilihan(),
            'filterUnit' => $unitId ?: null,
            'pilihanUnit' => OrganisationUnit::query()
                ->orderBy('jenis')
                ->orderBy('urutan')
                ->get(['id', 'nama', 'jenis'])
                ->map(fn (OrganisationUnit $unit): array => [
                    'id' => $unit->id,
                    'label' => $unit->labelJenis().' — '.$unit->nama,
                ]),
            'pilihanGambar' => $this->pilihanGambar(),
        ]);
    }

    /* ------------------------------ Album ------------------------------ */

    public function simpanAlbum(Request $request): RedirectResponse
    {
        $data = $this->validasiAlbum($request);

        $album = new Gallery;
        $album->unit_id = $data['unit_id'] ?? null;
        $album->tanggal = $data['tanggal'] ?? null;
        $album->lokasi = $data['lokasi'] ?? null;
        $album->publik = (bool) ($data['publik'] ?? true);
        $album->urutan = $data['urutan'] ?? 0;
        $album->setTranslations('judul', $this->bersihkan($data['judul'] ?? []));
        $album->setTranslations('slug', $this->slugDari($data['judul'] ?? [], null));
        $album->setTranslations('deskripsi', $this->bersihkan($data['deskripsi'] ?? []));
        $album->save();

        return back()->with('sukses', 'Album galeri dibuat. Silakan tambahkan foto.');
    }

    public function perbaruiAlbum(Request $request, Gallery $album): RedirectResponse
    {
        $data = $this->validasiAlbum($request);

        $album->unit_id = $data['unit_id'] ?? null;
        $album->tanggal = $data['tanggal'] ?? null;
        $album->lokasi = $data['lokasi'] ?? null;
        $album->publik = (bool) ($data['publik'] ?? true);
        $album->urutan = $data['urutan'] ?? 0;
        $album->setTranslations('judul', $this->bersihkan($data['judul'] ?? []));
        $album->setTranslations('deskripsi', $this->bersihkan($data['deskripsi'] ?? []));

        // Slug lama dipertahankan bila judul belum pernah diubah.
        if (blank($album->getTranslation('slug', 'id', false))) {
            $album->setTranslations('slug', $this->slugDari($data['judul'] ?? [], $album->id));
        }

        $album->save();

        return back()->with('sukses', 'Album galeri diperbarui.');
    }

    public function hapusAlbum(Gallery $album): RedirectResponse
    {
        $judul = (string) $album->getTranslation('judul', 'id', false);

        DB::transaction(function () use ($album): void {
            GalleryItem::query()->where('gallery_id', $album->id)->delete();
            $album->delete();
        });

        return back()->with('sukses', 'Album "'.$judul.'" beserta fotonya dihapus.');
    }

    /* ------------------------------ Foto ------------------------------ */

    public function simpanFoto(Request $request, Gallery $album): RedirectResponse
    {
        $data = $request->validate([
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'url' => ['nullable', 'url', 'max:500'],
            'keterangan' => ['nullable', 'array'],
            'keterangan.id' => ['nullable', 'string', 'max:255'],
            'keterangan.en' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'media_id.exists' => 'Gambar yang dipilih tidak ada di Pustaka Media.',
        ]);

        if (blank($data['media_id']) && blank($data['url'])) {
            return back()->with('galat', 'Pilih gambar dari Pustaka Media, atau tempelkan alamat gambar dari luar.');
        }

        $foto = new GalleryItem;
        $foto->gallery_id = $album->id;
        $foto->media_id = $data['media_id'] ?? null;
        $foto->url = $data['url'] ?? null;
        $foto->urutan = $data['urutan'] ?? ($album->item()->max('urutan') + 1);
        $foto->setTranslations('keterangan', $this->bersihkan($data['keterangan'] ?? []));
        $foto->save();

        return back()->with('sukses', 'Foto ditambahkan ke album.');
    }

    public function hapusFoto(GalleryItem $foto): RedirectResponse
    {
        $foto->delete();

        return back()->with('sukses', 'Foto dihapus dari album.');
    }

    /* ----------------------------- Agenda ----------------------------- */

    public function simpanAgenda(Request $request): RedirectResponse
    {
        $data = $this->validasiAgenda($request);

        $agenda = new UnitAgenda;
        $agenda->unit_id = $data['unit_id'] ?? null;
        $agenda->gambar_media_id = $data['gambar_media_id'] ?? null;
        $agenda->mulai = $data['mulai'];
        $agenda->selesai = $data['selesai'] ?? null;
        $agenda->lokasi = $data['lokasi'] ?? null;
        $agenda->publik = (bool) ($data['publik'] ?? true);
        $agenda->setTranslations('judul', $this->bersihkan($data['judul'] ?? []));
        $agenda->setTranslations('deskripsi', $this->bersihkan($data['deskripsi'] ?? []));
        $agenda->save();

        return back()->with('sukses', 'Agenda unit ditambahkan.');
    }

    public function perbaruiAgenda(Request $request, UnitAgenda $agenda): RedirectResponse
    {
        $data = $this->validasiAgenda($request);

        $agenda->unit_id = $data['unit_id'] ?? null;
        $agenda->gambar_media_id = $data['gambar_media_id'] ?? null;
        $agenda->mulai = $data['mulai'];
        $agenda->selesai = $data['selesai'] ?? null;
        $agenda->lokasi = $data['lokasi'] ?? null;
        $agenda->publik = (bool) ($data['publik'] ?? true);
        $agenda->setTranslations('judul', $this->bersihkan($data['judul'] ?? []));
        $agenda->setTranslations('deskripsi', $this->bersihkan($data['deskripsi'] ?? []));
        $agenda->save();

        return back()->with('sukses', 'Agenda unit diperbarui.');
    }

    public function hapusAgenda(UnitAgenda $agenda): RedirectResponse
    {
        $agenda->delete();

        return back()->with('sukses', 'Agenda dihapus.');
    }

    /* ---------------------------- Bantuan ---------------------------- */

    /**
     * @return array<string, mixed>
     */
    private function validasiAlbum(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:160'],
            'judul.en' => ['nullable', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'array'],
            'deskripsi.id' => ['nullable', 'string', 'max:2000'],
            'deskripsi.en' => ['nullable', 'string', 'max:2000'],
            'tanggal' => ['nullable', 'date'],
            'lokasi' => ['nullable', 'string', 'max:160'],
            'publik' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'judul.id.required' => 'Judul album (Indonesia) wajib diisi.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasiAgenda(Request $request): array
    {
        return $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:160'],
            'judul.en' => ['nullable', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'array'],
            'deskripsi.id' => ['nullable', 'string', 'max:2000'],
            'deskripsi.en' => ['nullable', 'string', 'max:2000'],
            'gambar_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'lokasi' => ['nullable', 'string', 'max:190'],
            'publik' => ['boolean'],
        ], [
            'judul.id.required' => 'Judul agenda (Indonesia) wajib diisi.',
            'selesai.after_or_equal' => 'Waktu selesai tidak boleh mendahului waktu mulai.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, string>
     */
    private function bersihkan(array $nilai): array
    {
        return array_filter($nilai, fn ($isi) => is_string($isi) && trim($isi) !== '');
    }

    /**
     * @param  array<string, mixed>  $judul
     * @return array<string, string>
     */
    private function slugDari(array $judul, ?int $kecualiId): array
    {
        $hasil = [];

        foreach (['id', 'en'] as $bahasa) {
            $teks = (string) ($judul[$bahasa] ?? '');

            if ($teks === '') {
                continue;
            }

            $hasil[$bahasa] = $this->slugUnik(Str::slug($teks), $bahasa, $kecualiId);
        }

        return $hasil;
    }

    private function slugUnik(string $dasar, string $bahasa, ?int $kecualiId): string
    {
        $dasar = $dasar !== '' ? $dasar : 'album';

        $slug = $dasar;
        $urutan = 2;

        while (Gallery::query()
            ->where("slug->{$bahasa}", $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$urutan;
            $urutan++;
        }

        return $slug;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pilihanGambar()
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'nama' => $media->name ?: $media->file_name,
                'url' => $media->hasGeneratedConversion('kecil') ? $media->getUrl('kecil') : $media->getUrl(),
            ]);
    }
}
