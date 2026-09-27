<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\OrganisationUnit;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Galeri foto publik.
 *
 * `/galeri`        → seluruh album yang ditandai publik
 * `/galeri/{slug}` → isi satu album
 */
class GaleriController extends Controller
{
    public function daftar(Request $request): View
    {
        $unit = $request->string('unit')->toString();

        return view('public.galeri-daftar', [
            'situs' => Pengaturan::semua(),
            'album' => Gallery::query()
                ->publik()
                ->with('unit:id,nama,jenis')
                ->withCount('item')
                ->when($unit !== '', fn ($q) => $q->whereRelation('unit', 'slug', $unit))
                ->urut()
                ->paginate(12)
                ->withQueryString(),
            'daftarUnit' => OrganisationUnit::query()
                ->aktif()
                ->orderBy('jenis')
                ->orderBy('urutan')
                ->get(['id', 'nama', 'slug', 'jenis']),
            'saringUnit' => $unit,
        ]);
    }

    public function detail(string $slug): View
    {
        $album = Gallery::query()
            ->publik()
            ->where('slug->id', $slug)
            ->with(['unit:id,nama,jenis,slug', 'item'])
            ->firstOrFail();

        return view('public.galeri-detail', [
            'situs' => Pengaturan::semua(),
            'album' => $album,
            'albumLain' => Gallery::query()
                ->publik()
                ->whereKeyNot($album->id)
                ->with('unit:id,nama')
                ->urut()
                ->limit(4)
                ->get(),
        ]);
    }
}
