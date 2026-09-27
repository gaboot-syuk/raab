<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Services\Hibah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hibah & dukungan — sisi alumni/kader.
 *
 * Alumni mengajukan dana, barang, atau jasa; Bendahara yang memutuskan dan
 * mencatat penerimaannya. Halaman ini TIDAK menyentuh kas maupun stok — ia
 * hanya membuat pengajuan, sehingga tidak ada uang yang bisa "masuk sendiri"
 * tanpa persetujuan Bendahara.
 */
class HibahController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Hibah $hibah) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        $milikSaya = $anggota
            ? Donation::query()->where('member_id', $anggota->id)->orderByDesc('id')->get()
            : collect();

        return Inertia::render('Anggota/Hibah', [
            'daftar' => $milikSaya->map(fn (Donation $d): array => [
                'id' => $d->id,
                'nomor_hibah' => $d->nomor_hibah,
                'judul' => $d->judulTeks(),
                'jenis' => $d->jenis,
                'label_jenis' => $d->labelJenis(),
                'nilai' => $d->nilaiTercatat(),
                'status' => $d->status,
                'label_status' => $d->labelStatus(),
                'alasan_tolak' => $d->alasan_tolak,
                'catatan_bendahara' => $d->catatan_bendahara,
                'tanggal_rencana' => $d->tanggal_rencana?->format('Y-m-d'),
                'diterima_pada' => $d->diterima_pada?->format('d M Y'),
            ])->all(),
            'pilihanJenis' => Donation::JENIS,
            'totalSaya' => (int) $milikSaya
                ->whereIn('status', [Donation::STATUS_DITERIMA, Donation::STATUS_DIVERIFIKASI])
                ->sum(fn (Donation $d): int => $d->nilaiTercatat()),
            'catatan' => 'Hibah dana akan tercatat sebagai kas masuk bertanda "hibah", hibah barang menambah stok inventaris rayon, dan hibah jasa dicatat pada kegiatan.',
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            return back()->with('galat', 'Halaman ini untuk anggota rayon.');
        }

        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Donation::JENIS))],
            'judul' => ['required', 'string', 'max:190'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'estimasi_nilai' => ['required', 'integer', 'min:0'],
            'tanggal_rencana' => ['nullable', 'date', 'after_or_equal:today'],
            'kontak' => ['nullable', 'string', 'max:160'],
            'anonim' => ['boolean'],
        ]);

        // Nama pemberi boleh kosong di formulir; diambil dari data anggota.
        $data['nama_pemberi'] = (string) $anggota->nama_lengkap;

        return $this->jalankan(
            fn () => $this->hibah->ajukan($data, $anggota, $request->user()),
            'Terima kasih! Pengajuan hibahmu sudah diterima dan akan ditinjau Bendahara.',
        );
    }

    /**
     * Batalkan pengajuan sendiri — selama belum diterima Bendahara.
     */
    public function batalkan(Request $request, Donation $hibah): RedirectResponse
    {
        $anggota = $request->user()->member;

        if (! $anggota || $hibah->member_id !== $anggota->id) {
            return back()->with('galat', 'Pengajuan ini bukan milikmu.');
        }

        if (in_array($hibah->status, [Donation::STATUS_DITERIMA, Donation::STATUS_DIVERIFIKASI], true)) {
            return back()->with('galat', 'Hibah yang sudah diterima tidak dapat dibatalkan sendiri — hubungi Bendahara.');
        }

        return $this->jalankan(
            fn () => $this->hibah->batalkan($hibah, $request->user(), 'Dibatalkan oleh pengaju.'),
            'Pengajuan hibah dibatalkan.',
        );
    }
}
