<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Member;
use App\Models\User;
use App\Services\Inventaris;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori, aset, dan mutasi inventaris.
 *
 * Jumlah aset TIDAK dapat disunting langsung dari sini — satu-satunya jalan
 * adalah mencatat mutasi, supaya setiap perubahan punya jejak dan penanggung
 * jawab. Tombol "Sunting" sengaja tidak menyediakan kolom jumlah.
 */
class InventarisController extends Controller
{
    public function __construct(
        private readonly Inventaris $inventaris,
    ) {}

    public function index(Request $request): Response
    {
        $saring = [
            'kategori' => $request->string('kategori')->toString(),
            'cari' => trim($request->string('cari')->toString()),
            'menipis' => $request->string('menipis')->toString(),
        ];

        $daftar = InventoryItem::query()
            ->with('kategori:id,nama')
            ->when($saring['kategori'] !== '', fn ($q) => $q->where('category_id', (int) $saring['kategori']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq->where('nama->id', 'like', "%{$saring['cari']}%")
                    ->orWhere('kode', 'like', "%{$saring['cari']}%"),
            ))
            ->when($saring['menipis'] === '1', fn ($q) => $q->where('jumlah_minimum', '>', 0)->whereColumn('jumlah', '<=', 'jumlah_minimum'))
            ->orderBy('nama->id')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'kode' => $item->kode,
                'nama' => $item->getTranslations('nama'),
                'keterangan' => $item->getTranslations('keterangan'),
                'kategori_id' => $item->category_id,
                'kategori' => $item->kategori?->namaTeks(),
                'satuan' => $item->satuan,
                'jumlah' => $item->jumlah,
                'jumlah_minimum' => $item->jumlah_minimum,
                'jumlah_dipinjam' => $item->jumlahDipinjam(),
                'jumlah_tersedia' => $item->jumlahTersedia(),
                'kondisi' => $item->kondisi,
                'label_kondisi' => $item->labelKondisi(),
                'lokasi' => $item->lokasi,
                'nilai' => $item->nilai,
                'foto_media_id' => $item->foto_media_id,
                'is_public' => $item->is_public,
                'aktif' => $item->aktif,
                'stok_menipis' => $item->stokMenipis(),
            ]);

        return Inertia::render('Panel/Inventaris/Index', [
            'daftar' => $daftar,
            'pilihanKategori' => InventoryCategory::query()->orderBy('urutan')->get()
                ->map(fn (InventoryCategory $k): array => ['id' => $k->id, 'nama' => $k->namaTeks(), 'slug' => $k->slug, 'aktif' => $k->aktif]),
            'jenisMutasi' => InventoryMovement::JENIS,
            'kondisi' => InventoryItem::KONDISI,
            'pilihanAnggota' => Member::query()->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])
                ->orderBy('nama_lengkap')
                ->limit(500)
                ->get(['id', 'nama_lengkap'])
                ->map(fn (Member $m): array => ['id' => $m->id, 'label' => $m->nama_lengkap]),
            'riwayat' => InventoryMovement::query()
                ->with(['aset:id,kode,nama,satuan', 'penanggungJawab:id,nama_lengkap', 'pencatat:id,name'])
                ->orderByDesc('terjadi_pada')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->map(fn (InventoryMovement $m): array => [
                    'id' => $m->id,
                    'aset' => $m->aset?->namaTeks(),
                    'kode_aset' => $m->aset?->kode,
                    'jenis' => $m->jenis,
                    'label_jenis' => $m->labelJenis(),
                    'jumlah' => $m->jumlah,
                    'jumlah_sebelum' => $m->jumlah_sebelum,
                    'jumlah_sesudah' => $m->jumlah_sesudah,
                    'penanggung_jawab' => $m->namaPenanggungJawab(),
                    'pencatat' => $m->pencatat?->name,
                    'catatan' => $m->catatan,
                    'terjadi_pada' => $m->terjadi_pada?->translatedFormat('d M Y, H:i'),
                ]),
            'saring' => $saring,
        ]);
    }

    /* ---------------------------- Kategori ---------------------------- */

    public function simpanKategori(Request $request): RedirectResponse
    {
        $data = $this->validasiKategori($request);

        $kategori = new InventoryCategory;
        $kategori->slug = InventoryCategory::slugUnik($data['nama']['id']);
        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = (bool) ($data['aktif'] ?? true);
        $kategori->setTranslations('nama', $this->bersihkan($data['nama']));
        $kategori->save();

        return back()->with('sukses', 'Kategori '.$kategori->namaTeks().' ditambahkan.');
    }

    public function perbaruiKategori(Request $request, InventoryCategory $kategori): RedirectResponse
    {
        $data = $this->validasiKategori($request);

        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = (bool) ($data['aktif'] ?? true);
        $kategori->setTranslations('nama', $this->bersihkan($data['nama']));
        $kategori->save();

        return back()->with('sukses', 'Kategori diperbarui.');
    }

    public function hapusKategori(InventoryCategory $kategori): RedirectResponse
    {
        if ($kategori->aset()->exists()) {
            return back()->with('galat', 'Kategori ini masih dipakai '.$kategori->aset()->count().' aset. Pindahkan asetnya lebih dulu.');
        }

        $kategori->delete();

        return back()->with('sukses', 'Kategori dihapus.');
    }

    /* ------------------------------- Aset ------------------------------- */

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasiAset($request);

        $item = new InventoryItem;
        $item->kode = $data['kode'];
        $item->category_id = $data['category_id'] ?? null;
        $item->satuan = $data['satuan'] ?? 'buah';
        // Stok awal dicatat sebagai mutasi "barang masuk", bukan diisi langsung,
        // supaya riwayatnya lengkap sejak hari pertama.
        $item->jumlah = 0;
        $item->jumlah_minimum = $data['jumlah_minimum'] ?? 0;
        $item->kondisi = $data['kondisi'];
        $item->lokasi = $data['lokasi'] ?? null;
        $item->nilai = (int) ($data['nilai'] ?? 0);
        $item->foto_media_id = $data['foto_media_id'] ?? null;
        $item->is_public = (bool) ($data['is_public'] ?? false);
        $item->aktif = (bool) ($data['aktif'] ?? true);
        $item->setTranslations('nama', $this->bersihkan($data['nama']));
        $item->setTranslations('keterangan', $this->bersihkan($data['keterangan'] ?? []));
        $item->save();

        $awal = (int) ($data['jumlah_awal'] ?? 0);

        if ($awal > 0) {
            $this->inventaris->catat($item, InventoryMovement::JENIS_MASUK, $awal, [
                'catatan' => 'Stok awal saat aset didaftarkan.',
            ], $request->user());
        }

        return back()->with('sukses', 'Aset '.$item->namaTeks().' didaftarkan.');
    }

    public function perbarui(Request $request, InventoryItem $aset): RedirectResponse
    {
        $data = $this->validasiAset($request, $aset);

        $aset->kode = $data['kode'];
        $aset->category_id = $data['category_id'] ?? null;
        $aset->satuan = $data['satuan'] ?? 'buah';
        $aset->jumlah_minimum = $data['jumlah_minimum'] ?? 0;
        $aset->kondisi = $data['kondisi'];
        $aset->lokasi = $data['lokasi'] ?? null;
        $aset->nilai = (int) ($data['nilai'] ?? 0);
        $aset->foto_media_id = $data['foto_media_id'] ?? null;
        $aset->is_public = (bool) ($data['is_public'] ?? false);
        $aset->aktif = (bool) ($data['aktif'] ?? true);
        $aset->setTranslations('nama', $this->bersihkan($data['nama']));
        $aset->setTranslations('keterangan', $this->bersihkan($data['keterangan'] ?? []));
        $aset->save();

        return back()->with('sukses', 'Aset '.$aset->namaTeks().' diperbarui. Jumlah stok hanya berubah lewat mutasi.');
    }

    public function hapus(InventoryItem $aset): RedirectResponse
    {
        if ($aset->mutasi()->exists()) {
            return back()->with('galat', 'Aset ini sudah memiliki mutasi. Nonaktifkan saja agar riwayatnya tetap utuh.');
        }

        $nama = $aset->namaTeks();
        $aset->delete();

        return back()->with('sukses', 'Aset '.$nama.' dihapus.');
    }

    /* ------------------------------ Mutasi ------------------------------ */

    public function catatMutasi(Request $request, InventoryItem $aset): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(InventoryMovement::JENIS))],
            'jumlah' => ['required', 'integer', 'min:1', 'max:100000'],
            'penanggung_jawab_id' => ['nullable', 'integer', 'exists:members,id'],
            'penanggung_jawab_nama' => ['nullable', 'string', 'max:160'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'terjadi_pada' => ['nullable', 'date'],
        ], [
            'jumlah.min' => 'Jumlah mutasi harus lebih dari nol.',
        ]);

        try {
            $this->inventaris->catat($aset, $data['jenis'], (int) $data['jumlah'], $data, $request->user());
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', 'Mutasi tercatat. Stok '.$aset->namaTeks().' kini '.$aset->fresh()->jumlah.' '.$aset->satuan.'.');
    }

    /* ------------------------------ Bantuan ------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validasiKategori(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'array'],
            'nama.id' => ['required', 'string', 'max:120'],
            'nama.en' => ['nullable', 'string', 'max:120'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], ['nama.id.required' => 'Nama kategori (Indonesia) wajib diisi.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasiAset(Request $request, ?InventoryItem $aset = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:60', Rule::unique('inventory_items', 'kode')->ignore($aset?->id)],
            'nama' => ['required', 'array'],
            'nama.id' => ['required', 'string', 'max:160'],
            'nama.en' => ['nullable', 'string', 'max:160'],
            'keterangan' => ['nullable', 'array'],
            'keterangan.id' => ['nullable', 'string', 'max:1000'],
            'keterangan.en' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'satuan' => ['nullable', 'string', 'max:24'],
            'jumlah_awal' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'jumlah_minimum' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'kondisi' => ['required', Rule::in(array_keys(InventoryItem::KONDISI))],
            'lokasi' => ['nullable', 'string', 'max:160'],
            'nilai' => ['nullable', 'integer', 'min:0'],
            'foto_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_public' => ['boolean'],
            'aktif' => ['boolean'],
        ], [
            'kode.unique' => 'Kode aset itu sudah dipakai.',
            'nama.id.required' => 'Nama aset (Indonesia) wajib diisi.',
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
}
