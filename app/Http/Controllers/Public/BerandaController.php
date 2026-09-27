<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
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
     * Daftar LSO untuk kartu di beranda.
     * Pada Fase 1 masih memakai data placeholder agar tampilan tidak kosong.
     *
     * @return array<int, array<string, mixed>>
     */
    private function daftarLso(): array
    {
        return [
            ['nama' => 'Mutasi', 'bidang' => __('umum.lso.mutasi'), 'warna' => 'primary'],
            ['nama' => 'Harokatuna', 'bidang' => __('umum.lso.harokatuna'), 'warna' => 'accent'],
            ['nama' => 'LDR', 'bidang' => __('umum.lso.ldr'), 'warna' => 'primary-500'],
            ['nama' => 'LPM Albiruni', 'bidang' => __('umum.lso.albiruni'), 'warna' => 'danger'],
            ['nama' => 'MJT', 'bidang' => __('umum.lso.mjt'), 'warna' => 'primary-800'],
        ];
    }
}
