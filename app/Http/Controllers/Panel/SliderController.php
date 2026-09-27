<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\SliderRequest;
use App\Models\MediaLibrary;
use App\Models\Slider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Slider "Tampilan Utama" pada beranda.
 *
 * Setiap slider dapat dijadwalkan (mulai/berakhir) sehingga pengurus bisa
 * menyiapkan tampilan jauh hari tanpa harus membuka panel tepat waktu.
 */
class SliderController extends Controller
{
    public function index(): Response
    {
        $daftar = Slider::query()
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map(fn (Slider $slider): array => [
                'id' => $slider->id,
                'judul' => $slider->getTranslations('judul'),
                'subjudul' => $slider->getTranslations('subjudul'),
                'label_tombol' => $slider->getTranslations('label_tombol'),
                'tautan_tombol' => $slider->tautan_tombol,
                'media_id' => $slider->media_id,
                'gambar' => $slider->media_id
                    ? Media::query()->find($slider->media_id)?->getUrl()
                    : null,
                'urutan' => $slider->urutan,
                'aktif' => $slider->aktif,
                'mulai_pada' => $slider->mulai_pada?->format('Y-m-d'),
                'berakhir_pada' => $slider->berakhir_pada?->format('Y-m-d'),
                'sedang_tampil' => $slider->aktif
                    && (! $slider->mulai_pada || $slider->mulai_pada->isPast())
                    && (! $slider->berakhir_pada || $slider->berakhir_pada->isFuture()),
            ]);

        return Inertia::render('Panel/Slider/Index', [
            'daftar' => $daftar,
            'pilihanGambar' => $this->pilihanGambar(),
        ]);
    }

    public function simpan(SliderRequest $request): RedirectResponse
    {
        $slider = new Slider;
        $this->isi($slider, $request);
        $slider->dibuat_oleh = $request->user()?->id;
        $slider->save();

        return back()->with('sukses', 'Slider berhasil ditambahkan.');
    }

    public function perbarui(SliderRequest $request, Slider $slider): RedirectResponse
    {
        $this->isi($slider, $request);
        $slider->save();

        return back()->with('sukses', 'Slider berhasil diperbarui.');
    }

    public function hapus(Slider $slider): RedirectResponse
    {
        $slider->delete();

        return back()->with('sukses', 'Slider dihapus.');
    }

    /**
     * Simpan urutan baru hasil geser-seret di panel.
     */
    public function urutkan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'urutan' => ['required', 'array'],
            'urutan.*' => ['integer', 'exists:sliders,id'],
        ]);

        foreach ($data['urutan'] as $posisi => $id) {
            Slider::query()->whereKey($id)->update(['urutan' => $posisi + 1]);
        }

        return back()->with('sukses', 'Urutan slider disimpan.');
    }

    /**
     * Terapkan data formulir ke model (dua bahasa).
     */
    private function isi(Slider $slider, SliderRequest $request): void
    {
        $data = $request->validated();

        foreach (['judul', 'subjudul', 'label_tombol'] as $kolom) {
            $nilai = array_filter(
                $data[$kolom] ?? [],
                fn ($isi) => is_string($isi) && trim($isi) !== '',
            );

            $slider->setTranslations($kolom, $nilai);
        }

        $slider->tautan_tombol = $data['tautan_tombol'] ?? null;
        $slider->media_id = $data['media_id'] ?? null;
        $slider->urutan = $data['urutan'] ?? 0;
        $slider->aktif = (bool) ($data['aktif'] ?? false);
        $slider->mulai_pada = $data['mulai_pada'] ?? null;
        $slider->berakhir_pada = $data['berakhir_pada'] ?? null;
    }

    /**
     * Daftar gambar pada pustaka media, untuk pemilih gambar.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pilihanGambar()
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereIn('collection_name', array_keys(MediaLibrary::KOLEKSI))
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
