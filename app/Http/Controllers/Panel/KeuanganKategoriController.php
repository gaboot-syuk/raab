<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategori keuangan bertingkat.
 *
 * Kategori yang sudah dipakai transaksi TIDAK dihapus — hanya dinonaktifkan.
 * Menghapusnya akan membuat laporan lama kehilangan labelnya, dan angka yang
 * tidak berlabel justru lebih berbahaya daripada kategori yang tidak rapi.
 */
class KeuanganKategoriController extends Controller
{
    use MenjalankanAksi;

    public function index(): Response
    {
        $semua = FinanceCategory::query()
            ->with('induk')
            ->orderBy('jenis')->orderBy('urutan')->orderBy('kode')
            ->get();

        return Inertia::render('Panel/Keuangan/Kategori', [
            'daftar' => $semua->map(fn (FinanceCategory $k): array => [
                'id' => $k->id,
                'kode' => $k->kode,
                'nama' => $k->namaTeks(),
                'label_lengkap' => $k->labelLengkap(),
                'jenis' => $k->jenis,
                'label_jenis' => $k->labelJenis(),
                'parent_id' => $k->parent_id,
                'induk' => $k->induk?->namaTeks(),
                'urutan' => $k->urutan,
                'aktif' => $k->aktif,
                'jumlah_transaksi' => $k->transaksi()->count(),
                'terpakai' => $k->transaksi()->exists() || $k->anggaran()->exists(),
            ])->all(),
            'pilihanJenis' => FinanceCategory::JENIS,
            // Hanya kategori induk yang boleh jadi atasan, dan hanya yang sejenis.
            'pilihanInduk' => $semua->whereNull('parent_id')
                ->map(fn (FinanceCategory $k): array => [
                    'id' => $k->id,
                    'nama' => $k->namaTeks(),
                    'jenis' => $k->jenis,
                ])->values()->all(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(array_keys(FinanceCategory::JENIS))],
            'parent_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $this->periksaInduk($data['parent_id'] ?? null, $data['jenis']);

        $kategori = new FinanceCategory;
        $kategori->kode = $this->kodeUnik($data['nama']);
        $kategori->jenis = $data['jenis'];
        $kategori->parent_id = $data['parent_id'] ?? null;
        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = true;
        $kategori->setTranslations('nama', ['id' => $data['nama']]);

        if (filled($data['keterangan'] ?? null)) {
            $kategori->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $kategori->save();

        return back()->with('sukses', 'Kategori '.$kategori->labelLengkap().' ditambahkan.');
    }

    public function perbarui(Request $request, FinanceCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(array_keys(FinanceCategory::JENIS))],
            'parent_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'aktif' => ['boolean'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        if (($data['parent_id'] ?? null) === $kategori->id) {
            return back()->with('galat', 'Kategori tidak dapat menjadi induk bagi dirinya sendiri.');
        }

        $this->periksaInduk($data['parent_id'] ?? null, $data['jenis']);

        if ($data['jenis'] !== $kategori->jenis && $kategori->transaksi()->exists()) {
            return back()->with('galat', 'Jenis kategori tidak dapat diubah karena sudah dipakai transaksi.');
        }

        $kategori->jenis = $data['jenis'];
        $kategori->parent_id = $data['parent_id'] ?? null;
        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = (bool) ($data['aktif'] ?? $kategori->aktif);
        $kategori->setTranslations('nama', ['id' => $data['nama']]);

        if (filled($data['keterangan'] ?? null)) {
            $kategori->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $kategori->save();

        return back()->with('sukses', 'Kategori diperbarui.');
    }

    public function hapus(FinanceCategory $kategori): RedirectResponse
    {
        if ($kategori->transaksi()->exists() || $kategori->anggaran()->exists()) {
            return back()->with('galat', 'Kategori ini sudah dipakai. Nonaktifkan saja agar laporan lama tetap berlabel.');
        }

        if ($kategori->anak()->exists()) {
            return back()->with('galat', 'Kategori ini masih punya sub-kategori. Pindahkan atau hapus sub-kategorinya dulu.');
        }

        $nama = $kategori->namaTeks();
        $kategori->delete();

        return back()->with('sukses', 'Kategori '.$nama.' dihapus.');
    }

    /**
     * Kode kategori dibuat dari namanya, dan dijaga tetap unik.
     */
    private function kodeUnik(string $nama): string
    {
        $dasar = Str::upper(Str::slug($nama, '_')) ?: 'KATEGORI';

        $kode = $dasar;
        $urutan = 2;

        while (FinanceCategory::query()->where('kode', $kode)->exists()) {
            $kode = $dasar.'_'.$urutan;
            $urutan++;
        }

        return $kode;
    }

    /**
     * Sub-kategori harus sejenis dengan induknya — kalau tidak, laporan
     * penerimaan dan pengeluaran akan saling bercampur.
     */
    private function periksaInduk(?int $parentId, string $jenis): void
    {
        if (! $parentId) {
            return;
        }

        $induk = FinanceCategory::query()->find($parentId);

        if ($induk && $induk->jenis !== $jenis) {
            throw ValidationException::withMessages([
                'parent_id' => 'Induk kategori harus sejenis ('.$induk->labelJenis().').',
            ]);
        }
    }
}
