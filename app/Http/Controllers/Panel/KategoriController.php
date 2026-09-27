<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori & tag artikel.
 *
 * Digabung dalam satu halaman karena keduanya adalah alat pengelompokan yang
 * penggunaannya berbarengan saat menyunting artikel.
 */
class KategoriController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel/Artikel/Kategori', [
            'kategori' => ArticleCategory::query()
                ->orderBy('urutan')
                ->orderBy('id')
                ->get()
                ->map(fn (ArticleCategory $kategori): array => [
                    'id' => $kategori->id,
                    'nama_id' => $kategori->getTranslation('nama', 'id', false),
                    'nama_en' => $kategori->getTranslation('nama', 'en', false),
                    'slug_id' => $kategori->getTranslation('slug', 'id', false),
                    'urutan' => $kategori->urutan,
                    'aktif' => $kategori->aktif,
                    'jumlah_terbit' => $kategori->jumlahTerbit(),
                ]),
            'tag' => Tag::query()
                ->populer()
                ->limit(60)
                ->get()
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'nama' => $tag->getTranslation('nama', 'id', false),
                    'dipakai' => $tag->dipakai,
                ]),
        ]);
    }

    public function simpanKategori(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_id' => ['required', 'string', 'max:120'],
            'nama_en' => ['nullable', 'string', 'max:120'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['nama_id' => 'nama kategori (Indonesia)']);

        $kategori = new ArticleCategory;

        $kategori->setTranslations('nama', array_filter([
            'id' => $data['nama_id'],
            'en' => $data['nama_en'] ?? null,
        ]));

        $kategori->setTranslations('slug', array_filter([
            'id' => Str::slug($data['nama_id']),
            'en' => filled($data['nama_en'] ?? null) ? Str::slug($data['nama_en']) : null,
        ]));

        $kategori->urutan = $data['urutan'] ?? (ArticleCategory::query()->max('urutan') + 1);
        $kategori->aktif = true;
        $kategori->save();

        return back()->with('sukses', 'Kategori "'.$data['nama_id'].'" ditambahkan.');
    }

    public function perbaruiKategori(Request $request, ArticleCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'nama_id' => ['required', 'string', 'max:120'],
            'nama_en' => ['nullable', 'string', 'max:120'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['boolean'],
        ]);

        $kategori->setTranslations('nama', array_filter([
            'id' => $data['nama_id'],
            'en' => $data['nama_en'] ?? null,
        ]));

        $kategori->setTranslations('slug', array_filter([
            'id' => Str::slug($data['nama_id']),
            'en' => filled($data['nama_en'] ?? null) ? Str::slug($data['nama_en']) : null,
        ]));

        $kategori->urutan = $data['urutan'] ?? $kategori->urutan;
        $kategori->aktif = (bool) ($data['aktif'] ?? $kategori->aktif);
        $kategori->save();

        return back()->with('sukses', 'Kategori diperbarui.');
    }

    public function hapusKategori(ArticleCategory $kategori): RedirectResponse
    {
        // Kategori dihapus → artikelnya tetap ada, hanya kehilangan kategori.
        $nama = $kategori->getTranslation('nama', 'id', false);
        $kategori->delete();

        return back()->with('sukses', 'Kategori "'.$nama.'" dihapus. Artikelnya tetap tersimpan tanpa kategori.');
    }

    public function hapusTag(Tag $tag): RedirectResponse
    {
        $nama = $tag->getTranslation('nama', 'id', false);
        $tag->delete();

        return back()->with('sukses', 'Tag "'.$nama.'" dihapus.');
    }
}
