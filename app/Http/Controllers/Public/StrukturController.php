<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\PositionAssignment;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Bagan struktur kepengurusan.
 *
 * Menampilkan periode yang sedang berjalan secara bawaan; pengunjung dapat
 * memilih periode lain lewat ?periode=ID. Data periode lama tidak pernah
 * tersentuh — hanya dibaca.
 *
 * Bagan disusun per TINGKAT (level jabatan), bukan per unit, karena pertanyaan
 * pertama yang muncul saat membuka halaman ini selalu "siapa ketuanya".
 */
class StrukturController extends Controller
{
    public function __invoke(Request $request): View
    {
        $daftarPeriode = Period::query()
            ->orderByDesc('tahun_selesai')
            ->orderByDesc('tahun_mulai')
            ->get(['id', 'nama', 'tahun_mulai', 'tahun_selesai', 'aktif']);

        $diminta = $request->integer('periode');

        $periode = $diminta > 0
            ? $daftarPeriode->firstWhere('id', $diminta)
            : ($daftarPeriode->firstWhere('aktif', true) ?? $daftarPeriode->first());

        $tingkat = $periode
            ? $this->tingkat($periode->id)
            : collect();

        return view('public.struktur', [
            'situs' => Pengaturan::semua(),
            'daftarPeriode' => $daftarPeriode,
            'periode' => $periode,
            'tingkat' => $tingkat,
            'jumlahPengurus' => $tingkat->sum(fn (array $t): int => count($t['pengurus'])),
        ]);
    }

    /**
     * Susun pengurus per tingkat jabatan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function tingkat(int $periodeId): Collection
    {
        return PositionAssignment::query()
            ->where('position_assignments.period_id', $periodeId)
            ->where('position_assignments.aktif', true)
            ->with(['jabatan:id,nama,level,urutan,unit_id', 'jabatan.unit:id,nama,jenis', 'member:id,nama_lengkap,slug,foto_media_id'])
            ->join('positions', 'positions.id', '=', 'position_assignments.position_id')
            ->orderBy('positions.level')
            ->orderBy('positions.urutan')
            ->orderBy('position_assignments.urutan')
            ->orderBy('position_assignments.id')
            ->select('position_assignments.*')
            ->get()
            ->groupBy(fn (PositionAssignment $item): int => (int) ($item->jabatan?->level ?? 99))
            ->map(fn (Collection $baris, int $level): array => [
                'level' => $level,
                'nama' => $this->labelLevel($level),
                'pengurus' => $baris->map(fn (PositionAssignment $item): array => [
                    'id' => $item->id,
                    'jabatan' => $item->jabatan?->nama,
                    'unit' => $item->jabatan?->unit?->nama,
                    'nama' => $item->namaTampil(),
                    'keterangan' => $item->keterangan,
                    'slug' => $item->member?->slug,
                ])->values()->all(),
            ])
            ->values();
    }

    private function labelLevel(int $level): string
    {
        return match ($level) {
            1 => 'Pimpinan',
            2 => 'Pengurus Harian',
            3 => 'Kepala Biro & Lembaga',
            default => 'Lain-lain',
        };
    }
}
