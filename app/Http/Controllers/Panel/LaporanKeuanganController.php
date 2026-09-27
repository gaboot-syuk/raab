<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan keuangan.
 *
 * SELURUH LAPORAN DIHITUNG DARI TRANSAKSI TERKONFIRMASI. Tidak ada angka yang
 * disimpan terpisah, sehingga mustahil laporan berbeda dari buku kas.
 *
 * Ekspor memakai CSV, bukan Excel (.xlsx), karena container ini tidak punya
 * ekstensi zip yang dibutuhkan pustaka penulis xlsx. CSV tetap terbuka rapi di
 * Excel, LibreOffice, dan Google Sheets — lihat docs/05-roadmap-fase.md.
 */
class LaporanKeuanganController extends Controller
{
    public function index(Request $request): Response
    {
        $saring = $this->saring($request);
        $data = $this->hitung($saring);

        return Inertia::render('Panel/Keuangan/Laporan', [
            'saring' => $saring,
            'pilihanAkun' => FinanceAccount::query()->urut()->get()
                ->map(fn (FinanceAccount $a): array => [
                    'id' => $a->id,
                    'nama' => $a->namaTeks(),
                    'saldo' => $a->saldo_berjalan,
                ])->all(),
            'pilihanPeriode' => $this->pilihanPeriode(),
            'bukuKas' => $data['buku_kas'],
            'arusBulanan' => $data['arus_bulanan'],
            'rekapKategori' => $data['rekap_kategori'],
            'anggaran' => $data['anggaran'],
            'ringkasan' => $data['ringkasan'],
            'catatan' => 'Semua angka dihitung dari transaksi yang sudah dikonfirmasi. Draft dan transaksi void tidak pernah masuk hitungan.',
        ]);
    }

    /**
     * Unduh laporan sebagai CSV.
     */
    public function ekspor(Request $request): StreamedResponse
    {
        $saring = $this->saring($request);
        $data = $this->hitung($saring);

        $dariLabel = $saring['dari'] ?? 'awal';
        $sampaiLabel = $saring['sampai'] ?? 'akhir';
        $nama = 'laporan-keuangan-'.$dariLabel.'-'.$sampaiLabel.'.csv';

        return response()->streamDownload(function () use ($data): void {
            $keluaran = fopen('php://output', 'w');

            // BOM agar Excel di Windows membaca UTF-8 dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['BUKU KAS']);
            fputcsv($keluaran, ['Tanggal', 'Voucher', 'Akun', 'Kategori', 'Jenis', 'Sumber', 'Keterangan', 'Masuk', 'Keluar', 'Saldo Setelah']);

            foreach ($data['buku_kas'] as $baris) {
                fputcsv($keluaran, [
                    $baris['tanggal'],
                    $baris['nomor_voucher'],
                    $baris['akun'],
                    $baris['kategori'],
                    $baris['label_jenis'],
                    $baris['sumber'],
                    $baris['keterangan'],
                    $baris['jenis'] === FinanceTransaction::JENIS_MASUK ? $baris['jumlah'] : '',
                    $baris['jenis'] === FinanceTransaction::JENIS_KELUAR ? $baris['jumlah'] : '',
                    $baris['saldo_sesudah'],
                ]);
            }

            fputcsv($keluaran, []);
            fputcsv($keluaran, ['RINGKASAN']);
            fputcsv($keluaran, ['Saldo awal periode', $data['ringkasan']['saldo_awal']]);
            fputcsv($keluaran, ['Total masuk', $data['ringkasan']['total_masuk']]);
            fputcsv($keluaran, ['Total keluar', $data['ringkasan']['total_keluar']]);
            fputcsv($keluaran, ['Saldo akhir', $data['ringkasan']['saldo_akhir']]);

            fputcsv($keluaran, []);
            fputcsv($keluaran, ['REKAP PER KATEGORI']);
            fputcsv($keluaran, ['Kategori', 'Jenis', 'Total']);

            foreach ($data['rekap_kategori'] as $baris) {
                fputcsv($keluaran, [$baris['kategori'], $baris['label_jenis'], $baris['total']]);
            }

            fputcsv($keluaran, []);
            fputcsv($keluaran, ['ANGGARAN VS REALISASI']);
            fputcsv($keluaran, ['Anggaran', 'Rencana', 'Realisasi', 'Sisa', 'Persen']);

            foreach ($data['anggaran'] as $baris) {
                fputcsv($keluaran, [
                    $baris['nama'], $baris['rencana'], $baris['realisasi'], $baris['sisa'], $baris['persen'].'%',
                ]);
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function saring(Request $request): array
    {
        $awalBulan = Carbon::now()->startOfMonth();

        return [
            'akun' => $request->string('akun')->toString(),
            'dari' => $request->string('dari')->toString() ?: $awalBulan->format('Y-m-d'),
            'sampai' => $request->string('sampai')->toString() ?: Carbon::now()->format('Y-m-d'),
            'periode' => $request->string('periode')->toString(),
        ];
    }

    /**
     * Hitung seluruh angka laporan untuk rentang yang diminta.
     *
     * @param  array<string, mixed>  $saring
     * @return array<string, mixed>
     */
    private function hitung(array $saring): array
    {
        $dari = Carbon::parse($saring['dari'])->startOfDay();
        $sampai = Carbon::parse($saring['sampai'])->endOfDay();

        $dasar = fn () => FinanceTransaction::query()
            ->terkonfirmasi()
            ->when($saring['akun'] !== '', fn ($q) => $q->where('account_id', $saring['akun']));

        // Buku kas: transaksi di dalam rentang, urut waktu.
        $bukuKas = $dasar()
            ->with(['akun', 'kategori'])
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->orderBy('tanggal')->orderBy('id')
            ->get()
            ->map(fn (FinanceTransaction $t): array => [
                'id' => $t->id,
                'tanggal' => $t->tanggal?->format('Y-m-d'),
                'nomor_voucher' => $t->nomor_voucher,
                'akun' => $t->akun?->namaTeks() ?? '—',
                'kategori' => $t->kategori?->labelLengkap() ?? '—',
                'jenis' => $t->jenis,
                'label_jenis' => $t->labelJenis(),
                'sumber' => $t->labelSumber(),
                'keterangan' => $t->keterangan,
                'jumlah' => $t->jumlah,
                'saldo_sesudah' => $t->saldo_sesudah,
            ])->all();

        $totalMasuk = (int) $dasar()->where('jenis', FinanceTransaction::JENIS_MASUK)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])->sum('jumlah');
        $totalKeluar = (int) $dasar()->where('jenis', FinanceTransaction::JENIS_KELUAR)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])->sum('jumlah');

        // Saldo awal = saldo akun dikurangi seluruh pergerakan SEJAK tanggal awal.
        $saldoAwalAkun = FinanceAccount::query()
            ->when($saring['akun'] !== '', fn ($q) => $q->whereKey($saring['akun']))
            ->sum('saldo_awal');

        $pergerakanSebelum = FinanceTransaction::query()->terkonfirmasi()
            ->when($saring['akun'] !== '', fn ($q) => $q->where('account_id', $saring['akun']))
            ->where('tanggal', '<', $dari->toDateString())
            ->get()
            ->sum(fn (FinanceTransaction $t): int => $t->arah() * $t->jumlah);

        $saldoAwal = (int) $saldoAwalAkun + (int) $pergerakanSebelum;

        return [
            'buku_kas' => $bukuKas,
            'arus_bulanan' => $this->arusBulanan($dasar(), $dari, $sampai),
            'rekap_kategori' => $this->rekapKategori($dasar(), $dari, $sampai),
            'anggaran' => $this->anggaran($saring),
            'ringkasan' => [
                'saldo_awal' => $saldoAwal,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_akhir' => $saldoAwal + $totalMasuk - $totalKeluar,
                'jumlah_transaksi' => count($bukuKas),
            ],
        ];
    }

    /**
     * Arus kas per bulan — dipakai melihat tren.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<FinanceTransaction>  $dasar
     * @return array<int, array<string, mixed>>
     */
    private function arusBulanan($dasar, Carbon $dari, Carbon $sampai): array
    {
        $transaksi = $dasar
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->get(['tanggal', 'jenis', 'jumlah']);

        return $transaksi
            ->groupBy(fn (FinanceTransaction $t): string => $t->tanggal->format('Y-m'))
            ->map(function ($grup, string $bulan): array {
                $masuk = (int) $grup->where('jenis', FinanceTransaction::JENIS_MASUK)->sum('jumlah');
                $keluar = (int) $grup->where('jenis', FinanceTransaction::JENIS_KELUAR)->sum('jumlah');

                return [
                    'bulan' => $bulan,
                    'label' => Carbon::parse($bulan.'-01')->translatedFormat('F Y'),
                    'masuk' => $masuk,
                    'keluar' => $keluar,
                    'selisih' => $masuk - $keluar,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinanceTransaction>  $dasar
     * @return array<int, array<string, mixed>>
     */
    private function rekapKategori($dasar, Carbon $dari, Carbon $sampai): array
    {
        return $dasar
            ->with('kategori.induk')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->get()
            ->groupBy(fn (FinanceTransaction $t): string => ($t->kategori?->labelLengkap() ?? 'Tanpa kategori').'|'.$t->jenis)
            ->map(function ($grup): array {
                /** @var FinanceTransaction $contoh */
                $contoh = $grup->first();

                return [
                    'kategori' => $contoh->kategori?->labelLengkap() ?? 'Tanpa kategori',
                    'jenis' => $contoh->jenis,
                    'label_jenis' => $contoh->labelJenis(),
                    'total' => (int) $grup->sum('jumlah'),
                    'jumlah_transaksi' => $grup->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $saring
     * @return array<int, array<string, mixed>>
     */
    private function anggaran(array $saring): array
    {
        $query = Budget::query()->with(['periode', 'kategori', 'kegiatan']);

        // Bila periode dipilih, anggaran disaring lewat period_id.
        if ($saring['periode'] !== '') {
            $query->where('period_id', $saring['periode']);
        }

        return $query->orderBy('jenis')->get()
            ->map(fn (Budget $b): array => [
                'id' => $b->id,
                'nama' => $b->namaTeks(),
                'jenis' => $b->jenis,
                'label_jenis' => Budget::JENIS[$b->jenis] ?? $b->jenis,
                'kategori' => $b->kategori?->labelLengkap(),
                'kegiatan' => $b->kegiatan?->judulTeks(),
                'periode' => $b->periode?->nama,
                'rencana' => $b->jumlah_direncanakan,
                'realisasi' => $b->realisasi(),
                'sisa' => $b->sisa(),
                'persen' => $b->persenRealisasi(),
                'melebihi' => $b->melebihiRencana(),
            ])->all();
    }

    /**
     * Pilihan periode kepengurusan untuk penyaring laporan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pilihanPeriode(): array
    {
        return Period::query()->orderByDesc('mulai')->get()
            ->map(fn (Period $p): array => ['id' => $p->id, 'nama' => $p->nama ?? ('Periode '.$p->id)])->all();
    }
}
