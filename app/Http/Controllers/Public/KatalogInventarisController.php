<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Katalog inventaris publik — HANYA BACA.
 *
 * Cuma barang bertanda `is_public` yang tampil. Barang internal rayon (berkas,
 * perlengkapan sekretariat) tidak pernah ikut terkirim ke peramban.
 */
class KatalogInventarisController extends Controller
{
    public function __invoke(Request $request): View
    {
        $saring = [
            'kategori' => $request->string('kategori')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $daftar = InventoryItem::query()
            ->publik()
            ->with('kategori:id,nama,slug')
            ->when($saring['kategori'] !== '', fn ($q) => $q->whereRelation('kategori', 'slug', $saring['kategori']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where('nama->id', 'like', "%{$saring['cari']}%"))
            ->orderBy('nama->id')
            ->get();

        return view('public.inventaris', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'saring' => $saring,
            'daftarKategori' => InventoryCategory::query()->aktif()->orderBy('urutan')->get(),
            'totalNilai' => $daftar->sum(fn (InventoryItem $item): int => $item->nilai * $item->jumlah),
        ]);
    }
}
