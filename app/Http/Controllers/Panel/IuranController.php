<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\DueCategory;
use App\Models\DueInvoice;
use App\Models\DuePayment;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Services\Iuran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Iuran anggota: kategori, penerbitan tagihan, pembayaran, dan rekap.
 *
 * Yang paling sering dikerjakan Bendahara adalah MEMVERIFIKASI bukti transfer,
 * jadi daftar pembayaran yang menunggu diletakkan paling atas.
 */
class IuranController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Iuran $iuran) {}

    public function index(Request $request): Response
    {
        $kategoriId = $request->integer('kategori') ?: DueCategory::query()->aktif()->urut()->value('id');
        $periode = $request->string('periode')->toString() ?: now()->format('Y-m');

        $kategori = $kategoriId ? DueCategory::query()->find($kategoriId) : null;

        $rekap = $kategori ? $this->iuran->rekap($kategori, $periode) : null;

        return Inertia::render('Panel/Keuangan/Iuran', [
            'kategori' => DueCategory::query()->urut()->get()->map(fn (DueCategory $k): array => [
                'id' => $k->id,
                'kode' => $k->kode,
                'nama' => $k->namaTeks(),
                'target' => $k->labelTarget(),
                'target_audiens' => $k->target_audiens,
                'nominal' => $k->nominal,
                'periode' => $k->labelPeriode(),
                'aktif' => $k->aktif,
                'jumlah_tagihan' => $k->tagihan()->count(),
                'jumlah_penerima' => $this->iuran->penerima($k)->count(),
            ])->all(),
            'pilihanTarget' => DueCategory::TARGET,
            'pilihanPeriode' => DueCategory::PERIODE,
            'terpilih' => $kategori ? [
                'id' => $kategori->id,
                'nama' => $kategori->namaTeks(),
                'nominal' => $kategori->nominal,
            ] : null,
            'periode' => $periode,
            'rekap' => $rekap ? [
                'lunas' => $rekap['lunas'],
                'belum' => $rekap['belum'],
                'menunggu' => $rekap['menunggu'],
                'nominal_terkumpul' => $rekap['nominal_terkumpul'],
                'nominal_target' => $rekap['nominal_target'],
                // Yang benar-benar dapat diingatkan: belum bayar atau buktinya
                // ditolak. Yang berstatus "menunggu" sedang diperiksa Bendahara.
                'bisa_diingatkan' => $rekap['tagihan']
                    ->whereIn('status', [DueInvoice::STATUS_BELUM, DueInvoice::STATUS_DITOLAK])->count(),
                'tagihan' => $rekap['tagihan']->map(fn (DueInvoice $t): array => [
                    'id' => $t->id,
                    'anggota' => $t->anggota?->nama_lengkap ?? '—',
                    'nominal' => $t->nominal,
                    'status' => $t->status,
                    'label_status' => $t->labelStatus(),
                    'jatuh_tempo' => $t->jatuh_tempo?->format('Y-m-d'),
                    'terlambat' => $t->terlambat(),
                    'dibebaskan_alasan' => $t->dibebaskan_alasan,
                    'pengingat_terakhir_pada' => $t->pengingat_terakhir_pada?->translatedFormat('d M Y, H:i'),
                    'pengingat_terkirim' => $t->pengingat_terkirim,
                ])->all(),
            ] : null,
            // Bukti transfer yang menunggu — pekerjaan utama Bendahara.
            'menungguVerifikasi' => DuePayment::query()
                ->menunggu()
                ->with(['tagihan.kategori', 'anggota'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (DuePayment $p): array => [
                    'id' => $p->id,
                    'anggota' => $p->anggota?->nama_lengkap ?? '—',
                    'kategori' => $p->tagihan?->kategori?->namaTeks(),
                    'periode_label' => $p->tagihan?->periode_label,
                    'jumlah' => $p->jumlah,
                    'metode' => $p->labelMetode(),
                    'bukti_media_id' => $p->bukti_media_id,
                    'catatan_pembayar' => $p->catatan_pembayar,
                    'dibuat' => $p->created_at?->translatedFormat('d M Y, H:i'),
                ])->all(),
            'pilihanAkun' => $this->pilihanAkun(),
            'pilihanKategoriMasuk' => FinanceCategory::query()->jenis(FinanceCategory::JENIS_MASUK)->aktif()
                ->orderBy('urutan')->get()
                ->map(fn (FinanceCategory $k): array => ['id' => $k->id, 'nama' => $k->labelLengkap()])->all(),
            'catatan' => 'Transfer baru dianggap lunas setelah buktinya diverifikasi. Memverifikasi sekaligus mencatat kas masuk — satu pembayaran, satu transaksi.',
        ]);
    }

    public function simpanKategori(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'target_audiens' => ['required', Rule::in(array_keys(DueCategory::TARGET))],
            'nominal' => ['required', 'integer', 'min:1'],
            'periode' => ['required', Rule::in(array_keys(DueCategory::PERIODE))],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $kategori = new DueCategory;
        $kategori->kode = $this->kodeUnik($data['nama']);
        $kategori->target_audiens = $data['target_audiens'];
        $kategori->nominal = $data['nominal'];
        $kategori->periode = $data['periode'];
        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = true;
        $kategori->setTranslations('nama', ['id' => $data['nama']]);
        $kategori->save();

        return back()->with('sukses', 'Kategori iuran '.$kategori->namaTeks().' dibuat.');
    }

    public function perbaruiKategori(Request $request, DueCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'target_audiens' => ['required', Rule::in(array_keys(DueCategory::TARGET))],
            'nominal' => ['required', 'integer', 'min:1'],
            'periode' => ['required', Rule::in(array_keys(DueCategory::PERIODE))],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'aktif' => ['boolean'],
        ]);

        // Nominal pada kategori hanya dipakai untuk TAGIHAN BARU. Tagihan yang
        // sudah terbit tidak ikut berubah — jumlah yang pernah ditagih ke
        // anggota tidak boleh berubah diam-diam di belakang mereka.
        $kategori->target_audiens = $data['target_audiens'];
        $kategori->nominal = $data['nominal'];
        $kategori->periode = $data['periode'];
        $kategori->urutan = $data['urutan'] ?? 0;
        $kategori->aktif = (bool) ($data['aktif'] ?? $kategori->aktif);
        $kategori->setTranslations('nama', ['id' => $data['nama']]);
        $kategori->save();

        return back()->with('sukses', 'Kategori iuran diperbarui. Nominal baru berlaku untuk tagihan berikutnya.');
    }

    public function terbitkan(Request $request, DueCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'periode_label' => ['required', 'string', 'max:24'],
            'jatuh_tempo' => ['nullable', 'date'],
        ]);

        try {
            $jumlah = $this->iuran->terbitkan($kategori, $data['periode_label'], $data['jatuh_tempo'] ?? null, $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with('sukses', $jumlah > 0
            ? $jumlah.' tagihan periode '.$data['periode_label'].' diterbitkan.'
            : 'Tidak ada tagihan baru — semua penerima sudah punya tagihan untuk periode ini.');
    }

    /**
     * Kirim pengingat MANUAL ke anggota yang belum tuntas pada satu periode.
     *
     * Hasilnya dilaporkan apa adanya, termasuk yang DILEWATI — supaya Bendahara
     * tidak menyimpulkan "tombolnya rusak" ketika angkanya nol.
     */
    public function ingatkan(Request $request, DueCategory $kategori): RedirectResponse
    {
        $data = $request->validate([
            'periode_label' => ['required', 'string', 'max:24'],
        ]);

        $hasil = $this->iuran->ingatkan($kategori, $data['periode_label'], $request->user());

        return back()->with('sukses', $this->pesanPengingat($hasil, $data['periode_label']));
    }

    public function catatTunai(Request $request, DueInvoice $tagihan): RedirectResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
        ]);

        return $this->jalankan(
            fn () => $this->iuran->catatTunai($tagihan, $data['jumlah'], $request->user(), $data),
            'Pembayaran tunai dicatat, tagihan lunas, dan kas masuk terbentuk.',
        );
    }

    public function verifikasi(Request $request, DuePayment $pembayaran): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
        ]);

        return $this->jalankan(
            fn () => $this->iuran->verifikasi($pembayaran, $request->user(), $data),
            'Bukti diverifikasi. Tagihan lunas dan kas masuk tercatat.',
        );
    }

    public function tolakPembayaran(Request $request, DuePayment $pembayaran): RedirectResponse
    {
        $data = $request->validate([
            'catatan_bendahara' => ['required', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->iuran->tolak($pembayaran, $request->user(), $data['catatan_bendahara']),
            'Bukti ditolak. Anggota diminta mengunggah ulang.',
        );
    }

    public function bebaskan(Request $request, DueInvoice $tagihan): RedirectResponse
    {
        $data = $request->validate([
            'dibebaskan_alasan' => ['required', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->iuran->bebaskan($tagihan, $request->user(), $data['dibebaskan_alasan']),
            'Tagihan ditandai lunas tanpa pembayaran, beserta alasannya.',
        );
    }

    /**
     * Kalimat laporan setelah pengingat dikirim.
     *
     * @param  array{terkirim: int, menunggu_verifikasi: int, tanpa_akun: int, baru_diingatkan: int, total: int}  $hasil
     */
    private function pesanPengingat(array $hasil, string $periode): string
    {
        if ($hasil['total'] === 0) {
            return 'Tidak ada yang perlu diingatkan pada periode '.$periode.' — seluruh tagihannya sudah tuntas.';
        }

        $bagian = [];

        $bagian[] = $hasil['terkirim'].' pengingat terkirim';

        if ($hasil['menunggu_verifikasi'] > 0) {
            $bagian[] = $hasil['menunggu_verifikasi'].' dilewati karena buktinya sedang diperiksa';
        }

        if ($hasil['baru_diingatkan'] > 0) {
            $bagian[] = $hasil['baru_diingatkan'].' dilewati karena baru diingatkan dalam '
                .Iuran::JEDA_PENGINGAT_JAM.' jam terakhir';
        }

        if ($hasil['tanpa_akun'] > 0) {
            $bagian[] = $hasil['tanpa_akun'].' anggota belum punya akun untuk dikirimi';
        }

        return 'Pengingat iuran periode '.$periode.': '.implode('; ', $bagian).'.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pilihanAkun(): array
    {
        return FinanceAccount::query()->aktif()->urut()->get()
            ->map(fn (FinanceAccount $a): array => ['id' => $a->id, 'nama' => $a->namaTeks()])->all();
    }

    private function kodeUnik(string $nama): string
    {
        $dasar = Str::upper(Str::slug($nama, '_')) ?: 'IURAN';

        $kode = $dasar;
        $urutan = 2;

        while (DueCategory::query()->where('kode', $kode)->exists()) {
            $kode = $dasar.'_'.$urutan;
            $urutan++;
        }

        return $kode;
    }
}
