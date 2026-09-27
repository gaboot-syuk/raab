<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\ContributionPoint;
use App\Services\Kontribusi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Poin kontribusi dari sisi kader.
 *
 * DUA HAL YANG DITAMPILKAN, dan keduanya penting secara berbeda:
 *  - POSISI di papan peringkat, karena itulah yang memotivasi;
 *  - RINCIAN ASAL POIN, karena itulah yang membuat angkanya bisa dipercaya.
 *
 * Kader hanya melihat datanya SENDIRI. Papan peringkat lengkap bukan konsumsi
 * area anggota: nama kader beserta keaktifannya adalah data internal rayon,
 * dan menyiarkannya ke semua anggota membuat perbandingan antarorang menjadi
 * terbuka tanpa persetujuan mereka.
 */
class KontribusiController extends Controller
{
    public function __construct(private Kontribusi $kontribusi) {}

    public function index(Request $request): Response
    {
        $anggota = $request->user()->member;

        if ($anggota === null) {
            return Inertia::render('Anggota/Kontribusi', [
                'anggota' => false,
                'total' => 0,
                'posisi' => null,
                'rincianSumber' => [],
                'catatan' => [],
            ]);
        }

        $periode = $request->string('periode')->toString() ?: null;

        $catatan = ContributionPoint::query()
            ->with('kegiatan:id,kode,judul')
            ->where('member_id', $anggota->id)
            ->when($periode, fn ($q) => $q->periode($periode))
            ->orderByDesc('terjadi_pada')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ContributionPoint $p): array => [
                'id' => $p->id,
                'sumber' => $p->sumber,
                'label_sumber' => $p->labelSumber(),
                'poin' => $p->poinSah(),
                // Baris yang dibatalkan tetap ditampilkan — menyembunyikannya
                // membuat dua angka poin kader berbeda tanpa penjelasan.
                'dibatalkan' => $p->dibatalkan(),
                'alasan_pembatalan' => $p->alasan_pembatalan,
                'keterangan' => $p->keterangan,
                'terjadi_pada' => $p->terjadi_pada?->translatedFormat('d F Y'),
                'periode' => $p->periode_label,
            ])->all();

        return Inertia::render('Anggota/Kontribusi', [
            'anggota' => true,
            'total' => $this->kontribusi->total($anggota, $periode),
            'posisi' => $this->kontribusi->posisi($anggota, $periode),
            'rincianSumber' => $this->kontribusi->rekapSumber($anggota, $periode),
            'pilihanPeriode' => $this->kontribusi->periodeTersedia(),
            'periode' => $periode,
            'catatan' => $catatan,
            'catatanHalaman' => 'Poin diberikan otomatis dari kehadiran kegiatan dan karya yang terbit. Bila kamu merasa ada yang keliru, hubungi Sekretaris rayon — setiap baris punya keterangan asalnya.',
        ]);
    }
}
