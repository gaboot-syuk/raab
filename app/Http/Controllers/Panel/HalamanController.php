<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\HalamanRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan halaman statis (Sejarah, Visi & Misi, Sambutan, dan halaman bebas).
 *
 * Seluruh isi disunting dalam dua bahasa dari satu formulir: Bahasa Indonesia
 * wajib, Bahasa Inggris opsional (lihat docs/10-lokalisasi-bilingual.md).
 */
class HalamanController extends Controller
{
    public function index(): Response
    {
        $halaman = Page::query()
            ->orderBy('tipe')
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page): array => [
                'id' => $page->id,
                'kunci' => $page->kunci,
                'tipe' => $page->tipe,
                'judul_id' => $page->getTranslation('judul', 'id', false),
                'judul_en' => $page->getTranslation('judul', 'en', false),
                'status' => $page->status,
                'kelengkapan_en' => $page->kelengkapanTerjemahan(),
                'terbit_pada' => $page->terbit_pada?->translatedFormat('d M Y'),
                'diperbarui_pada' => $page->updated_at?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('Panel/Halaman/Index', [
            'halaman' => $halaman,
        ]);
    }

    public function sunting(Page $page): Response
    {
        return Inertia::render('Panel/Halaman/Sunting', [
            'halaman' => [
                'id' => $page->id,
                'kunci' => $page->kunci,
                'tipe' => $page->tipe,
                'status' => $page->status,
                'judul' => $page->getTranslations('judul'),
                'slug' => $page->getTranslations('slug'),
                'ringkasan' => $page->getTranslations('ringkasan'),
                'konten' => $page->getTranslations('konten'),
                'seo_judul' => $page->getTranslations('seo_judul'),
                'seo_deskripsi' => $page->getTranslations('seo_deskripsi'),
                'kelengkapan_en' => $page->kelengkapanTerjemahan(),
            ],
            'tautan_publik' => $page->kunci ? route('public.'.$this->namaRutePublik($page->kunci)) : null,
        ]);
    }

    public function perbarui(HalamanRequest $request, Page $page): RedirectResponse
    {
        $data = $request->validated();

        foreach (['judul', 'slug', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi'] as $kolom) {
            $nilai = array_filter(
                $data[$kolom] ?? [],
                fn ($isi) => is_string($isi) && trim($isi) !== '',
            );

            $page->setTranslations($kolom, $nilai);
        }

        $page->status = $data['status'];
        $page->terbit_pada = $data['status'] === Page::STATUS_TERBIT
            ? ($page->terbit_pada ?? now())
            : null;
        $page->diperbarui_oleh = $request->user()?->id;
        $page->save();

        /*
         * Cache halaman publik dibuang SETELAH perubahan tersimpan.
         *
         * Halaman statis di-cache supaya tiap kunjungan tidak menembak basis
         * data. Tanpa pembuangan ini, pengurus akan menyimpan perbaikan lalu
         * melihat isi yang lama di situs — dan menyimpulkan penyimpanannya
         * gagal, padahal yang salah cuma cache-nya.
         */
        Page::lupakan($page->kunci);

        return back()->with('sukses', 'Halaman "'.$page->getTranslation('judul', 'id').'" berhasil disimpan.');
    }

    /**
     * Nama rute publik untuk halaman tetap.
     */
    private function namaRutePublik(string $kunci): string
    {
        return match ($kunci) {
            Page::TIPE_SEJARAH => 'sejarah',
            Page::TIPE_VISI_MISI => 'visi-misi',
            Page::TIPE_SAMBUTAN => 'sambutan',
            default => 'beranda',
        };
    }
}
