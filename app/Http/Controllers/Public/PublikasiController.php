<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Tag;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman publikasi: daftar, per tipe, dan halaman baca artikel.
 *
 * Hanya artikel berstatus "terbit" yang pernah diambil dari basis data —
 * draf naskah yang sedang direview tidak mungkin bocor lewat halaman ini.
 */
class PublikasiController extends Controller
{
    public function index(Request $request): View
    {
        $kategoriId = $request->string('kategori')->toString();
        $tagSlug = $request->string('tag')->toString();

        $daftar = Article::query()
            ->with(['kategori:id,nama', 'penulis:id,name'])
            ->terbit()
            ->whereIn('tipe', Article::TIPE_PUBLIK)
            ->when($kategoriId !== '', fn ($q) => $q->where('kategori_id', (int) $kategoriId))
            ->when($tagSlug !== '', fn ($q) => $q->whereHas(
                'tags',
                fn ($qq) => $qq->where('slug->id', $tagSlug)->orWhere('slug->en', $tagSlug),
            ))
            ->terbaru()
            ->paginate(12)
            ->withQueryString();

        return view('public.publikasi', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'unggulan' => Article::query()
                ->with(['kategori:id,nama'])
                ->terbit()
                ->whereIn('tipe', Article::TIPE_PUBLIK)
                ->unggulan()
                ->terbaru()
                ->limit(3)
                ->get(),
            'kategori' => ArticleCategory::denganJumlahTerbit(),
            'tag' => Tag::query()->populer()->limit(18)->get(),
            'tipe' => null,
            'saring' => ['kategori' => $kategoriId, 'tag' => $tagSlug],
        ]);
    }

    public function perTipe(Request $request, string $tipe): View
    {
        $this->pastikanTipeSah($tipe);

        $daftar = Article::query()
            ->with(['kategori:id,nama', 'penulis:id,name'])
            ->terbit()
            ->tipe($tipe)
            ->terbaru()
            ->paginate(12)
            ->withQueryString();

        return view('public.publikasi', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'unggulan' => collect(),
            'kategori' => ArticleCategory::denganJumlahTerbit(),
            'tag' => Tag::query()->populer()->limit(18)->get(),
            'tipe' => $tipe,
            'saring' => ['kategori' => '', 'tag' => ''],
        ]);
    }

    public function detail(Request $request, string $tipe, string $slug): View
    {
        $this->pastikanTipeSah($tipe);

        $artikel = Article::query()
            ->with(['kategori:id,nama', 'penulis:id,name', 'tags'])
            ->terbit()
            ->tipe($tipe)
            ->where(fn ($q) => $q->where('slug->id', $slug)->orWhere('slug->en', $slug))
            ->firstOrFail();

        // Penghitung dibaca: dinaikkan sekali per kunjungan halaman.
        $artikel->increment('dilihat');

        return view('public.artikel', [
            'situs' => Pengaturan::semua(),
            'artikel' => $artikel,
            'terkait' => Article::query()
                ->with(['kategori:id,nama'])
                ->terbit()
                ->whereIn('tipe', Article::TIPE_PUBLIK)
                ->whereKeyNot($artikel->id)
                ->when(
                    $artikel->kategori_id,
                    fn ($q) => $q->where('kategori_id', $artikel->kategori_id),
                )
                ->terbaru()
                ->limit(3)
                ->get(),
            'sebelumnya' => Article::query()->terbit()->tipe($tipe)
                ->where('id', '<', $artikel->id)->orderByDesc('id')->first(),
            'berikutnya' => Article::query()->terbit()->tipe($tipe)
                ->where('id', '>', $artikel->id)->orderBy('id')->first(),
        ]);
    }

    /**
     * Tipe harus salah satu dari lima tipe publik — berita acara tidak punya
     * halaman publik.
     */
    private function pastikanTipeSah(string $tipe): void
    {
        abort_unless(in_array($tipe, Article::TIPE_PUBLIK, true), 404);
    }
}
