<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Event;
use App\Models\FinanceAccount;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Services\Hibah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Hibah & dukungan alumni — sisi Bendahara.
 *
 * Menerima hibah bukan sekadar mengubah status. Jenis hibah menentukan modul
 * mana yang tersentuh: dana masuk kas, barang menambah stok inventaris, jasa
 * tercatat pada kegiatan. Karena itu formulir penerimaannya berbeda per jenis.
 */
class HibahController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Hibah $hibah) {}

    public function index(Request $request): Response
    {
        $saring = [
            'jenis' => $request->string('jenis')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $daftar = Donation::query()
            ->with(['anggota', 'transaksi', 'mutasi', 'kegiatan'])
            ->when($saring['jenis'] !== '', fn ($q) => $q->jenis($saring['jenis']))
            ->when($saring['status'] !== '', fn ($q) => $q->status($saring['status']))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Donation $d): array => [
                'id' => $d->id,
                'nomor_hibah' => $d->nomor_hibah,
                'judul' => $d->judulTeks(),
                'jenis' => $d->jenis,
                'label_jenis' => $d->labelJenis(),
                'pemberi' => $d->namaPemberi($request->user()),
                'anonim' => $d->anonim,
                'kontak' => $d->kontak,
                'nilai' => $d->nilaiTercatat(),
                'nilai_diterima' => $d->nilai_diterima,
                'status' => $d->status,
                'label_status' => $d->labelStatus(),
                'tanggal_rencana' => $d->tanggal_rencana?->format('Y-m-d'),
                'diterima_pada' => $d->diterima_pada?->format('Y-m-d'),
                'alasan_tolak' => $d->alasan_tolak,
                'catatan_bendahara' => $d->catatan_bendahara,
                // Jejak tujuan hibah — memperlihatkan ke mana tiap hibah bermuara.
                'ke_kas' => $d->transaksi?->nomor_voucher,
                'ke_inventaris' => $d->mutasi?->aset?->namaTeks(),
                'ke_kegiatan' => $d->kegiatan?->judulTeks(),
            ])->all();

        return Inertia::render('Panel/Keuangan/Hibah', [
            'daftar' => $daftar,
            'saring' => $saring,
            'rekap' => $this->hibah->rekap(),
            'pilihanJenis' => Donation::JENIS,
            'pilihanStatus' => Donation::STATUS,
            'pilihanAkun' => FinanceAccount::query()->aktif()->urut()->get()
                ->map(fn (FinanceAccount $a): array => ['id' => $a->id, 'nama' => $a->namaTeks()])->all(),
            'pilihanKategoriMasuk' => FinanceCategory::query()->jenis(FinanceCategory::JENIS_MASUK)->aktif()->orderBy('urutan')->get()
                ->map(fn (FinanceCategory $k): array => ['id' => $k->id, 'nama' => $k->labelLengkap()])->all(),
            'pilihanAset' => InventoryItem::query()->aktif()->orderBy('kode')->get()
                ->map(fn (InventoryItem $i): array => [
                    'id' => $i->id,
                    'nama' => $i->kode.' — '.$i->namaTeks().' (stok '.$i->jumlah.')',
                ])->all(),
            'pilihanKegiatan' => Event::query()->orderByDesc('id')->limit(100)->get()
                ->map(fn (Event $e): array => ['id' => $e->id, 'nama' => $e->judulTeks()])->all(),
            'pilihanBukti' => Media::query()
                ->orderByDesc('created_at')->limit(100)->get()
                ->map(fn (Media $m): array => ['id' => $m->id, 'nama' => $m->name ?: $m->file_name])->all(),
            'batasWajibBukti' => FinanceTransaction::BATAS_WAJIB_BUKTI,
            'catatan' => 'Hibah dana menambah saldo kas, hibah barang menambah stok inventaris, hibah jasa tercatat pada kegiatan. Ketiganya lewat layanan yang sudah menjaga jejaknya.',
        ]);
    }

    /**
     * Bendahara mencatat hibah langsung (mis. dana tunai diserahkan di sekretariat).
     */
    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Donation::JENIS))],
            'judul' => ['required', 'string', 'max:190'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'nama_pemberi' => ['required', 'string', 'max:160'],
            'kontak' => ['nullable', 'string', 'max:160'],
            'estimasi_nilai' => ['required', 'integer', 'min:0'],
            'tanggal_rencana' => ['nullable', 'date'],
            'anonim' => ['boolean'],
            'kondisi_barang' => ['nullable', Rule::in(array_keys(InventoryItem::KONDISI))],
        ]);

        return $this->jalankan(
            fn () => $this->hibah->catatLangsung($data, $request->user()),
            'Hibah dicatat. Lanjutkan dengan "Terima" untuk menyalurkannya ke kas, inventaris, atau kegiatan.',
        );
    }

    public function setujui(Request $request, Donation $hibah): RedirectResponse
    {
        $data = $request->validate(['catatan_bendahara' => ['nullable', 'string', 'max:500']]);

        return $this->jalankan(
            fn () => $this->hibah->setujui($hibah, $request->user(), $data['catatan_bendahara'] ?? null),
            'Hibah disetujui dan berstatus dijanjikan.',
        );
    }

    public function tolak(Request $request, Donation $hibah): RedirectResponse
    {
        $data = $request->validate(['alasan_tolak' => ['required', 'string', 'max:500']]);

        return $this->jalankan(
            fn () => $this->hibah->tolak($hibah, $request->user(), $data['alasan_tolak']),
            'Hibah ditolak beserta alasannya.',
        );
    }

    /**
     * Terima hibah — titik di mana jenisnya menentukan tujuan.
     */
    public function terima(Request $request, Donation $hibah): RedirectResponse
    {
        $data = $request->validate([
            'nilai_diterima' => ['nullable', 'integer', 'min:0'],
            'account_id' => ['nullable', 'integer', 'exists:finance_accounts,id'],
            'category_id' => ['nullable', 'integer', 'exists:finance_categories,id'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'kondisi_barang' => ['nullable', Rule::in(array_keys(InventoryItem::KONDISI))],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'bukti_media_id' => ['nullable', 'integer'],
            'catatan_bendahara' => ['nullable', 'string', 'max:500'],
        ]);

        $pesan = match ($hibah->jenis) {
            Donation::JENIS_DANA => 'Hibah dana diterima — kas masuk terbentuk dengan sumber "hibah".',
            Donation::JENIS_BARANG => 'Hibah barang diterima — stok inventaris bertambah lewat mutasi.',
            default => 'Hibah jasa diterima dan tercatat pada kegiatan.',
        };

        return $this->jalankan(
            fn () => $this->hibah->terima($hibah, $request->user(), $data),
            $pesan,
        );
    }

    public function batalkan(Request $request, Donation $hibah): RedirectResponse
    {
        $data = $request->validate(['catatan_bendahara' => ['required', 'string', 'max:500']]);

        return $this->jalankan(
            fn () => $this->hibah->batalkan($hibah, $request->user(), $data['catatan_bendahara']),
            'Hibah dibatalkan.',
        );
    }

    /**
     * Ekspor rekap hibah (CSV — lihat catatan pada LaporanKeuanganController).
     */
    public function ekspor(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $daftar = Donation::query()->orderBy('nomor_hibah')->get();

        return response()->streamDownload(function () use ($daftar): void {
            $keluaran = fopen('php://output', 'w');
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['Nomor', 'Tanggal Terima', 'Jenis', 'Pemberi', 'Judul', 'Nilai', 'Status']);

            foreach ($daftar as $d) {
                fputcsv($keluaran, [
                    $d->nomor_hibah,
                    $d->diterima_pada?->format('Y-m-d') ?? $d->tanggal_rencana?->format('Y-m-d'),
                    $d->labelJenis(),
                    $d->namaPemberi(),
                    $d->judulTeks(),
                    $d->nilaiTercatat(),
                    $d->labelStatus(),
                ]);
            }

            fclose($keluaran);
        }, 'rekap-hibah.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
