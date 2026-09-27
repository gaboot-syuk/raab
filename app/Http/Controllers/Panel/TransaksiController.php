<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Event;
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
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Buku kas: daftar transaksi, pencatatan, konfirmasi, dan void.
 *
 * Halaman ini sengaja TIDAK menyediakan tombol hapus. Satu-satunya cara
 * membatalkan transaksi adalah void beralasan — supaya kesalahan input pun
 * tercatat, bukan hilang.
 */
class TransaksiController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Kas $kas) {}

    public function index(Request $request): Response
    {
        $saring = [
            'akun' => $request->string('akun')->toString(),
            'jenis' => $request->string('jenis')->toString(),
            'status' => $request->string('status')->toString(),
            'cari' => trim($request->string('cari')->toString()),
            'bulan' => $request->string('bulan')->toString(),
        ];

        $daftar = FinanceTransaction::query()
            ->with(['akun', 'kategori'])
            ->when($saring['akun'] !== '', fn ($q) => $q->where('account_id', $saring['akun']))
            ->when($saring['jenis'] !== '', fn ($q) => $q->where('jenis', $saring['jenis']))
            ->when($saring['status'] !== '', fn ($q) => $q->where('status', $saring['status']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(function ($qq) use ($saring): void {
                $qq->where('nomor_voucher', 'like', '%'.$saring['cari'].'%')
                    ->orWhere('keterangan', 'like', '%'.$saring['cari'].'%');
            }))
            ->when($saring['bulan'] !== '', function ($q) use ($saring): void {
                $b = Carbon::parse($saring['bulan'].'-01');
                $q->whereYear('tanggal', $b->year)->whereMonth('tanggal', $b->month);
            })
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FinanceTransaction $t): array => [
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
                'punya_bukti' => $t->bukti_media_id !== null,
                'wajib_bukti' => $t->wajibBukti(),
            ]);

        return Inertia::render('Panel/Keuangan/Transaksi', [
            'daftar' => $daftar,
            'saring' => $saring,
            'pilihanAkun' => FinanceAccount::query()->aktif()->urut()->get()
                ->map(fn (FinanceAccount $a): array => [
                    'id' => $a->id,
                    'nama' => $a->namaTeks(),
                    'saldo' => $a->saldo_berjalan,
                ])->all(),
            'pilihanKategoriMasuk' => $this->pilihanKategori(FinanceCategory::JENIS_MASUK),
            'pilihanKategoriKeluar' => $this->pilihanKategori(FinanceCategory::JENIS_KELUAR),
            'pilihanSumber' => FinanceTransaction::SUMBER,
            'pilihanJenis' => FinanceTransaction::JENIS,
            'pilihanStatus' => FinanceTransaction::STATUS,
            'pilihanKegiatan' => Event::query()->orderByDesc('id')->limit(100)->get()
                ->map(fn (Event $e): array => ['id' => $e->id, 'nama' => $e->judulTeks()])->all(),
            'pilihanAnggaran' => Budget::query()->aktif()->get()
                ->map(fn (Budget $b): array => ['id' => $b->id, 'nama' => $b->namaTeks().' ('.$b->jenis.')'])->all(),
            'pilihanBukti' => Media::query()
                ->where('mime_type', 'like', 'image/%')
                ->orWhere('mime_type', 'like', 'application/pdf')
                ->orderByDesc('created_at')->limit(100)->get()
                ->map(fn (Media $m): array => ['id' => $m->id, 'nama' => $m->name ?: $m->file_name])->all(),
            'batasWajibBukti' => FinanceTransaction::BATAS_WAJIB_BUKTI,
            'catatan' => 'Nominal di atas Rp'.number_format(FinanceTransaction::BATAS_WAJIB_BUKTI, 0, ',', '.')
                .' wajib melampirkan bukti. Transaksi tidak dapat dihapus — hanya di-void disertai alasan.',
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'tanggal' => ['required', 'date'],
            'jenis' => ['required', Rule::in(array_keys(FinanceTransaction::JENIS))],
            'jumlah' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:500'],
            'sumber' => ['required', Rule::in(array_keys(FinanceTransaction::SUMBER))],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'bukti_media_id' => ['nullable', 'integer'],
        ]);

        // Pesan galatnya sudah ditulis rapi di layanan Kas (bukti wajib, kategori
        // tidak sejenis, dan seterusnya) — jadi cukup diteruskan.
        return $this->jalankan(
            fn () => $this->kas->catat($data, $request->user()),
            'Transaksi dicatat sebagai draft. Konfirmasi untuk mengubah saldo.',
        );
    }

    public function konfirmasi(Request $request, FinanceTransaction $transaksi): RedirectResponse
    {
        return $this->jalankan(
            fn () => $this->kas->konfirmasi($transaksi, $request->user()),
            'Transaksi '.$transaksi->nomor_voucher.' dikonfirmasi. Saldo akun diperbarui.',
        );
    }

    public function void(Request $request, FinanceTransaction $transaksi): RedirectResponse
    {
        $data = $request->validate([
            'void_alasan' => ['required', 'string', 'max:500'],
        ]);

        return $this->jalankan(
            fn () => $this->kas->void($transaksi, $request->user(), $data['void_alasan']),
            'Transaksi '.$transaksi->nomor_voucher.' di-void. Saldo dikoreksi dan alasannya tersimpan.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pilihanKategori(string $jenis): array
    {
        return FinanceCategory::query()
            ->jenis($jenis)->aktif()->with('induk')
            ->orderBy('urutan')->orderBy('kode')
            ->get()
            ->map(fn (FinanceCategory $k): array => [
                'id' => $k->id,
                'nama' => $k->labelLengkap(),
            ])->all();
    }
}
