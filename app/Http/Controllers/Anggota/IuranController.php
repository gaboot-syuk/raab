<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\DueInvoice;
use App\Models\DuePayment;
use App\Models\MediaLibrary;
use App\Models\Member;
use App\Services\Iuran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Iuran saya — kader, pengurus, dan alumni.
 *
 * Tanpa izin Spatie: yang menentukan adalah KEPEMILIKAN tagihan itu sendiri.
 * Setiap aksi memastikan tagihannya memang milik anggota yang sedang masuk,
 * sehingga tidak ada yang dapat membayar — atau mengaku sudah membayar — atas
 * nama orang lain.
 *
 * Anggota hanya boleh MENGIRIM bukti. Yang mengubah saldo kas adalah
 * verifikasi Bendahara, bukan unggahan di sini.
 */
class IuranController extends Controller
{
    use MenjalankanAksi;

    /**
     * Batas besar bukti transfer (KB).
     */
    private const MAKS_KB = 5120;

    /**
     * Jenis berkas yang diterima sebagai bukti transfer.
     *
     * @var array<int, string>
     */
    private const MIME = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf',
    ];

    public function __construct(private Iuran $iuran) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            abort(403, 'Halaman ini untuk anggota rayon.');
        }

        $tagihan = $this->tagihanMilik($anggota)->get();

        return Inertia::render('Anggota/Iuran', [
            'tagihan' => $tagihan->map(fn (DueInvoice $t): array => [
                'id' => $t->id,
                'kategori' => $t->kategori?->namaTeks(),
                'periode_label' => $t->periode_label,
                'nominal' => $t->nominal,
                'status' => $t->status,
                'label_status' => $t->labelStatus(),
                'jatuh_tempo' => $t->jatuh_tempo?->format('d M Y'),
                'terlambat' => $t->terlambat(),
                'tuntas' => $t->tuntas(),
                'dibebaskan_alasan' => $t->dibebaskan_alasan,
                // Bukti yang pernah diunggah & ditolak, agar anggota tahu apa
                // yang harus diperbaiki — bukan sekadar diminta ulang.
                'pembayaran' => $t->pembayaran->map(fn (DuePayment $p): array => [
                    'id' => $p->id,
                    'jumlah' => $p->jumlah,
                    'metode' => $p->labelMetode(),
                    'status' => $p->labelStatus(),
                    'catatan_bendahara' => $p->catatan_bendahara,
                    'dibuat' => $p->created_at?->translatedFormat('d M Y, H:i'),
                ])->all(),
            ])->all(),
            'ringkasan' => [
                'belum_bayar' => $tagihan->whereIn('status', [DueInvoice::STATUS_BELUM, DueInvoice::STATUS_DITOLAK])->count(),
                'menunggu' => $tagihan->where('status', DueInvoice::STATUS_MENUNGGU)->count(),
                'tunggakan' => (int) $tagihan->whereIn('status', [DueInvoice::STATUS_BELUM, DueInvoice::STATUS_DITOLAK])->sum('nominal'),
                'terlambat' => $tagihan->filter(fn (DueInvoice $t): bool => $t->terlambat())->count(),
            ],
            'batasMb' => (int) (self::MAKS_KB / 1024),
            'catatan' => 'Unggah bukti transfer di sini. Bendahara akan memeriksanya; setelah diverifikasi, tagihanmu lunas dan kas rayon bertambah.',
        ]);
    }

    /**
     * Kirim bukti transfer untuk satu tagihan milik sendiri.
     */
    public function kirimBukti(Request $request, DueInvoice $tagihan): RedirectResponse
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            abort(403, 'Halaman ini untuk anggota rayon.');
        }

        if ($tagihan->member_id !== $anggota->id) {
            return back()->with('galat', 'Tagihan ini bukan milikmu.');
        }

        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'catatan_pembayar' => ['nullable', 'string', 'max:500'],
            'bukti' => ['required', 'file', 'max:'.self::MAKS_KB, 'mimetypes:'.implode(',', self::MIME)],
        ], [], ['bukti' => 'bukti transfer']);

        $buktiId = $this->simpanBukti($request);

        return $this->jalankan(
            fn () => $this->iuran->ajukanTransfer(
                $tagihan,
                $data['jumlah'],
                $anggota,
                $buktiId,
                $data['catatan_pembayar'] ?? null,
            ),
            'Bukti terkirim. Bendahara akan memeriksanya, lalu tagihanmu ditandai lunas.',
        );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Tagihan milik seorang anggota, terbaru lebih dulu.
     *
     * @return \Illuminate\Database\Eloquent\Builder<DueInvoice>
     */
    private function tagihanMilik(Member $anggota)
    {
        return DueInvoice::query()
            ->where('member_id', $anggota->id)
            ->with(['kategori', 'pembayaran'])
            ->orderByDesc('periode_label')
            ->orderByDesc('id');
    }

    /**
     * Simpan berkas bukti ke pustaka media dan kembalikan id-nya.
     *
     * Anggota tidak memegang izin `media.upload`, jadi berkasnya ditempelkan
     * lewat jalur ini ke koleksi dokumen — bukan dibiarkan menempel bebas pada
     * tagihan, supaya seluruh berkas tetap terkelola di satu tempat.
     */
    private function simpanBukti(Request $request): int
    {
        $berkas = $request->file('bukti');

        $media = MediaLibrary::induk()
            ->addMedia($berkas)
            ->usingName('Bukti iuran '.$request->user()->name)
            /*
             * SENGAJA masih memakai disk 'public', dan itu BELUM selesai.
             *
             * Bukti pembayaran tidak pantas masuk ke bucket media yang dapat
             * dibaca siapa saja. Tetapi membiarkannya di disk 'public' berarti
             * berkasnya hilang setiap wadah dinyalakan ulang — di hosting
             * dengan sistem berkas sementara, keduanya sama-sama merugikan.
             *
             * Yang dibutuhkan: penyimpanan PERMANEN yang PRIVAT (bucket R2
             * terpisah, atau awalan privat dengan URL bertanda tangan). Itu
             * keputusan pengurus, bukan sesuatu yang pantas ditebak di sini.
             */
            ->toMediaCollection('dokumen', 'public');

        return (int) $media->id;
    }
}
