<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\OrganisationUnit;
use App\Models\Slider;
use App\Support\Pengaturan;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BerandaController extends Controller
{
    /**
     * Beranda publik — dirender server-side dengan Blade.
     *
     * Slider dan angka statistik dibaca dari basis data; bila pengurus belum
     * membuat slider, tampil teks bawaan agar beranda tidak pernah kosong.
     */
    public function index(): View
    {
        return view('public.beranda', [
            'slider' => $this->sliderTampil(),
            'statistik' => [
                ['label' => __('umum.statistik.anggota'), 'nilai' => Pengaturan::angka('stat_anggota', 0)],
                ['label' => __('umum.statistik.lso'), 'nilai' => Pengaturan::angka('stat_lso', 5)],
                ['label' => __('umum.statistik.biro'), 'nilai' => Pengaturan::angka('stat_biro', 8)],
                ['label' => __('umum.statistik.sejak'), 'nilai' => Pengaturan::angka('stat_sejak', 2017)],
            ],
            'lso' => $this->daftarLso(),
            'berita' => $this->beritaTerbaru(),
        ]);
    }

    /**
     * Tiga berita terbaru yang sudah terbit, untuk bagian "Berita Terbaru".
     *
     * Query dijalankan lewat scopeTerbit() sehingga draf dan artikel terjadwal
     * yang belum waktunya tidak mungkin muncul di beranda.
     *
     * @return Collection<int, Article>
     */
    private function beritaTerbaru(): Collection
    {
        return Article::query()
            ->with('kategori:id,nama')
            ->terbit()
            ->whereIn('tipe', Article::TIPE_PUBLIK)
            ->terbaru()
            ->limit(3)
            ->get();
    }

    /**
     * Slider aktif beserta URL gambarnya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sliderTampil(): Collection
    {
        $slider = Slider::query()->tampil()->get();

        if ($slider->isEmpty()) {
            return collect();
        }

        // Ambil seluruh gambar sekaligus agar tidak terjadi kueri berulang.
        $gambar = Media::query()
            ->whereIn('id', $slider->pluck('media_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $slider->map(fn (Slider $item): array => [
            'judul' => $item->judul,
            'subjudul' => $item->subjudul,
            'label_tombol' => $item->label_tombol,
            'tautan_tombol' => $item->tautan_tombol,
            'gambar' => $item->media_id ? $gambar->get($item->media_id)?->getUrl() : null,
        ]);
    }

    /**
     * Kartu LSO di beranda — dibaca dari BASIS DATA, bukan daftar tetap.
     *
     * Sebelumnya daftar ini ditulis tetap sebagai placeholder, dan itu
     * berbohong dua kali: beranda memamerkan lima LSO yang tidak ada di basis
     * data, sementara halaman /lso — yang membaca basis data — menjawab
     * "Belum ada data". Kartunya pun tidak bertaut ke mana pun, sehingga tidak
     * ada cara memeriksa mana yang benar.
     *
     * Sekarang sumbernya sama dengan halaman /lso, dan tiap kartu bertaut ke
     * halaman LSO-nya. Bila belum ada LSO sama sekali, beranda MENYEMBUNYIKAN
     * bagian ini alih-alih menjanjikan yang tidak ada.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function daftarLso(): Collection
    {
        return OrganisationUnit::query()
            ->lso()
            ->aktif()
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->map(fn (OrganisationUnit $unit): array => [
                'nama' => $unit->nama,
                'bidang' => $unit->getTranslation('deskripsi', app()->getLocale(), false)
                    ?: $unit->getTranslation('deskripsi', 'id', false),
                'tautan' => '/lso/'.$unit->slug,
            ]);
    }
}
