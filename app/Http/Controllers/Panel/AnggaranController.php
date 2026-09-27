<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anggaran (RKAT) dan realisasinya.
 *
 * Realisasi dihitung dari transaksi kas terkonfirmasi yang ditautkan ke
 * anggaran — bukan diketik manual. Dengan begitu "anggaran vs realisasi" tidak
 * mungkin berbeda dari buku kas.
 */
class AnggaranController extends Controller
{
    use MenjalankanAksi;

    public function index(Request $request): Response
    {
        $periodeId = $request->integer('periode') ?: Period::query()->where('aktif', true)->value('id');

        $daftar = Budget::query()
            ->with(['periode', 'kegiatan', 'kategori'])
            ->when($periodeId, fn ($q) => $q->where('period_id', $periodeId))
            ->orderBy('jenis')->orderBy('id')
            ->get();

        $baris = $daftar->map(fn (Budget $b): array => [
            'id' => $b->id,
            'nama' => $b->namaTeks(),
            'jenis' => $b->jenis,
            'label_jenis' => Budget::JENIS[$b->jenis] ?? $b->jenis,
            'periode' => $b->periode?->nama ?? null,
            'kegiatan' => $b->kegiatan?->judulTeks(),
            'kategori' => $b->kategori?->labelLengkap(),
            'rencana' => $b->jumlah_direncanakan,
            'realisasi' => $b->realisasi(),
            'sisa' => $b->sisa(),
            'persen' => $b->persenRealisasi(),
            'melebihi' => $b->melebihiRencana(),
            'aktif' => $b->aktif,
            'jumlah_transaksi' => $b->transaksi()->count(),
        ])->all();

        return Inertia::render('Panel/Keuangan/Anggaran', [
            'daftar' => $baris,
            'ringkasan' => [
                'rencana_masuk' => (int) $daftar->where('jenis', Budget::JENIS_MASUK)->sum('jumlah_direncanakan'),
                'rencana_keluar' => (int) $daftar->where('jenis', Budget::JENIS_KELUAR)->sum('jumlah_direncanakan'),
                'realisasi_masuk' => (int) collect($baris)->where('jenis', Budget::JENIS_MASUK)->sum('realisasi'),
                'realisasi_keluar' => (int) collect($baris)->where('jenis', Budget::JENIS_KELUAR)->sum('realisasi'),
            ],
            'periodeTerpilih' => $periodeId,
            'pilihanPeriode' => Period::query()->orderByDesc('mulai')->get()
                ->map(fn (Period $p): array => ['id' => $p->id, 'nama' => $p->nama ?? ('Periode '.$p->id)])->all(),
            'pilihanJenis' => Budget::JENIS,
            'pilihanKegiatan' => Event::query()->orderByDesc('id')->limit(100)->get()
                ->map(fn (Event $e): array => ['id' => $e->id, 'nama' => $e->judulTeks()])->all(),
            'pilihanKategori' => FinanceCategory::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get()
                ->map(fn (FinanceCategory $k): array => [
                    'id' => $k->id,
                    'nama' => $k->labelLengkap().' ('.$k->labelJenis().')',
                    'jenis' => $k->jenis,
                ])->all(),
            'catatan' => 'Realisasi tidak diketik manual — ia dijumlahkan dari transaksi kas yang sudah dikonfirmasi dan ditautkan ke anggaran ini.',
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $anggaran = new Budget;
        $anggaran->period_id = $data['period_id'] ?? null;
        $anggaran->event_id = $data['event_id'] ?? null;
        $anggaran->category_id = $data['category_id'] ?? null;
        $anggaran->jenis = $data['jenis'];
        $anggaran->jumlah_direncanakan = $data['jumlah_direncanakan'];
        $anggaran->aktif = true;
        $anggaran->setTranslations('nama', ['id' => $data['nama']]);

        if (filled($data['keterangan'] ?? null)) {
            $anggaran->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $anggaran->save();

        return back()->with('sukses', 'Anggaran '.$anggaran->namaTeks().' ditambahkan.');
    }

    public function perbarui(Request $request, Budget $anggaran): RedirectResponse
    {
        $data = $this->validasi($request);

        $anggaran->period_id = $data['period_id'] ?? null;
        $anggaran->event_id = $data['event_id'] ?? null;
        $anggaran->category_id = $data['category_id'] ?? null;
        $anggaran->jenis = $data['jenis'];
        $anggaran->jumlah_direncanakan = $data['jumlah_direncanakan'];
        $anggaran->aktif = (bool) ($data['aktif'] ?? $anggaran->aktif);
        $anggaran->setTranslations('nama', ['id' => $data['nama']]);

        if (filled($data['keterangan'] ?? null)) {
            $anggaran->setTranslations('keterangan', ['id' => $data['keterangan']]);
        }

        $anggaran->save();

        return back()->with('sukses', 'Anggaran diperbarui.');
    }

    public function hapus(Budget $anggaran): RedirectResponse
    {
        if ($anggaran->transaksi()->exists()) {
            // Sudah ada realisasi — angkanya tidak boleh hilang dari laporan.
            return back()->with('galat', 'Anggaran ini sudah punya realisasi. Nonaktifkan saja, jangan dihapus.');
        }

        $nama = $anggaran->namaTeks();
        $anggaran->delete();

        return back()->with('sukses', 'Anggaran '.$nama.' dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:160'],
            'jenis' => ['required', Rule::in(array_keys(Budget::JENIS))],
            'jumlah_direncanakan' => ['required', 'integer', 'min:0'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
