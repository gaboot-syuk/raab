<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Donation;
use App\Models\DueInvoice;
use App\Models\DuePayment;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Services\Kas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ikhtisar keuangan & pengelolaan akun kas.
 *
 * Ikhtisar sengaja menonjolkan HAL YANG PERLU DIKERJAKAN — transaksi yang masih
 * draft, pembayaran iuran yang menunggu verifikasi, dan anggaran yang sudah
 * melewati rencana. Angka total tanpa tindakan hanya membuat Bendahara menebak.
 */
class KeuanganController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Kas $kas) {}

    public function index(Request $request): Response
    {
        $akun = FinanceAccount::query()->urut()->get();

        $bendahara = $request->user();

        $bulanIni = Carbon::now();

        return Inertia::render('Panel/Keuangan/Index', [
            'akun' => $akun->map(fn (FinanceAccount $a): array => [
                'id' => $a->id,
                'kode' => $a->kode,
                'nama' => $a->namaTeks(),
                'jenis' => $a->jenis,
                'label_jenis' => $a->labelJenis(),
                'saldo_awal' => $a->saldo_awal,
                'saldo_berjalan' => $a->saldo_berjalan,
                'aktif' => $a->aktif,
                // Ditampilkan agar Bendahara bisa melihat sendiri bila ada yang
                // menulis saldo di luar layanan Kas.
                'saldo_sehat' => $this->kas->saldoSehat($a),
            ])->all(),
            'totalSaldo' => (int) $akun->sum('saldo_berjalan'),
            'ringkasan' => [
                'draft' => FinanceTransaction::query()->draft()->count(),
                'masuk_bulan_ini' => (int) FinanceTransaction::query()->terkonfirmasi()
                    ->where('jenis', FinanceTransaction::JENIS_MASUK)
                    ->whereYear('tanggal', $bulanIni->year)->whereMonth('tanggal', $bulanIni->month)
                    ->sum('jumlah'),
                'keluar_bulan_ini' => (int) FinanceTransaction::query()->terkonfirmasi()
                    ->where('jenis', FinanceTransaction::JENIS_KELUAR)
                    ->whereYear('tanggal', $bulanIni->year)->whereMonth('tanggal', $bulanIni->month)
                    ->sum('jumlah'),
                'iuran_menunggu' => DuePayment::query()->menunggu()->count(),
                'tagihan_belum' => DueInvoice::query()->belumTuntas()->count(),
                'hibah_diajukan' => Donation::query()->status(Donation::STATUS_DIAJUKAN)->count(),
            ],
            'bulan' => $bulanIni->translatedFormat('F Y'),
            'pilihanJenisAkun' => FinanceAccount::JENIS,
            'transaksiTerakhir' => FinanceTransaction::query()
                ->with(['akun', 'kategori'])
                ->orderByDesc('tanggal')->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (FinanceTransaction $t): array => $this->barisTransaksi($t))
                ->all(),
            'anggaranMelebihi' => Budget::query()->aktif()->get()
                ->filter(fn (Budget $b): bool => $b->melebihiRencana())
                ->map(fn (Budget $b): array => [
                    'id' => $b->id,
                    'nama' => $b->namaTeks(),
                    'rencana' => $b->jumlah_direncanakan,
                    'realisasi' => $b->realisasi(),
                ])->values()->all(),
            'jumlahKategori' => FinanceCategory::query()->count(),
            'catatan' => 'Saldo hanya bergerak lewat transaksi yang dikonfirmasi. Halaman ini menandai sendiri bila ada angka yang tidak cocok dengan buku kas.',
        ]);
    }

    /**
     * Daftar akun kas (halaman tersendiri).
     */
    public function akun(): Response
    {
        return Inertia::render('Panel/Keuangan/Akun', [
            'akun' => FinanceAccount::query()->urut()->get()->map(fn (FinanceAccount $a): array => [
                'id' => $a->id,
                'kode' => $a->kode,
                'nama' => $a->namaTeks(),
                'jenis' => $a->jenis,
                'label_jenis' => $a->labelJenis(),
                'saldo_awal' => $a->saldo_awal,
                'saldo_berjalan' => $a->saldo_berjalan,
                'saldo_seharusnya' => $this->kas->saldoSeharusnya($a),
                'aktif' => $a->aktif,
                'jumlah_transaksi' => $a->transaksi()->count(),
            ])->all(),
            'pilihanJenis' => FinanceAccount::JENIS,
        ]);
    }

    public function simpanAkun(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:40', 'unique:finance_accounts,kode'],
            'nama' => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(array_keys(FinanceAccount::JENIS))],
            'saldo_awal' => ['required', 'integer', 'min:0'],
            'urutan' => ['nullable', 'integer', 'min:0'],
        ]);

        $akun = new FinanceAccount;
        $akun->kode = $data['kode'];
        $akun->jenis = $data['jenis'];
        $akun->saldo_awal = $data['saldo_awal'];
        // Saldo berjalan dimulai sama dengan saldo awal.
        $akun->saldo_berjalan = $data['saldo_awal'];
        $akun->urutan = $data['urutan'] ?? 0;
        $akun->aktif = true;
        $akun->setTranslations('nama', ['id' => $data['nama']]);
        $akun->save();

        return back()->with('sukses', 'Akun kas '.$akun->namaTeks().' dibuat.');
    }

    public function perbaruiAkun(Request $request, FinanceAccount $akun): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:40', Rule::unique('finance_accounts', 'kode')->ignore($akun->id)],
            'nama' => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(array_keys(FinanceAccount::JENIS))],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'aktif' => ['boolean'],
        ]);

        // saldo_awal SENGAJA tidak dapat diubah: mengubahnya akan membuat seluruh
        // riwayat transaksi di atasnya tidak bisa direkonsiliasi lagi.
        $akun->kode = $data['kode'];
        $akun->jenis = $data['jenis'];
        $akun->urutan = $data['urutan'] ?? 0;
        $akun->aktif = (bool) ($data['aktif'] ?? $akun->aktif);
        $akun->setTranslations('nama', ['id' => $data['nama']]);
        $akun->save();

        return back()->with('sukses', 'Akun kas diperbarui.');
    }

    public function hapusAkun(FinanceAccount $akun): RedirectResponse
    {
        if ($akun->transaksi()->exists()) {
            // Akun yang sudah punya riwayat tidak dihapus — dinonaktifkan saja,
            // supaya catatannya tetap bisa ditelusuri.
            return back()->with('galat', 'Akun ini sudah punya riwayat transaksi. Nonaktifkan saja, jangan dihapus.');
        }

        $nama = $akun->namaTeks();
        $akun->delete();

        return back()->with('sukses', 'Akun kas '.$nama.' dihapus.');
    }

    /**
     * Satu baris transaksi untuk daftar.
     *
     * @return array<string, mixed>
     */
    private function barisTransaksi(FinanceTransaction $t): array
    {
        return [
            'id' => $t->id,
            'nomor_voucher' => $t->nomor_voucher,
            'tanggal' => $t->tanggal?->format('Y-m-d'),
            'jenis' => $t->jenis,
            'label_jenis' => $t->labelJenis(),
            'jumlah' => $t->jumlah,
            'keterangan' => $t->keterangan,
            'sumber' => $t->labelSumber(),
            'akun' => $t->akun?->namaTeks(),
            'kategori' => $t->kategori?->labelLengkap(),
            'status' => $t->status,
            'label_status' => $t->labelStatus(),
            'saldo_sebelum' => $t->saldo_sebelum,
            'saldo_sesudah' => $t->saldo_sesudah,
            'void_alasan' => $t->void_alasan,
            'tanpa_bukti' => $t->bukti_media_id === null,
        ];
    }
}
