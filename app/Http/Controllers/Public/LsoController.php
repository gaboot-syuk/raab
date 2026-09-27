<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Models\Period;
use App\Models\PositionAssignment;
use App\Models\UnitAgenda;
use App\Support\Pengaturan;
use Illuminate\View\View;

/**
 * Halaman publik Biro & Lembaga Semi Otonom.
 *
 * `/lso`         → daftar seluruh LSO
 * `/lso/{slug}`  → profil satu LSO: pengurus, anggota, galeri, agenda
 *
 * Unit nonaktif TIDAK dapat dibuka — kalau masih mau dibaca, aktifkan kembali.
 */
class LsoController extends Controller
{
    public function daftar(): View
    {
        return view('public.lso-daftar', [
            'situs' => Pengaturan::semua(),
            'daftar' => OrganisationUnit::query()
                ->lso()
                ->aktif()
                ->orderBy('urutan')
                ->orderBy('nama')
                ->withCount(['anggota', 'galeri'])
                ->get(),
        ]);
    }

    public function detail(string $slug): View
    {
        $unit = OrganisationUnit::query()
            ->lso()
            ->aktif()
            ->where('slug', $slug)
            ->firstOrFail();

        $periode = Period::sedangAktif()
            ?? Period::query()->orderByDesc('tahun_selesai')->first();

        return view('public.lso-detail', [
            'situs' => Pengaturan::semua(),
            'unit' => $unit,
            'periode' => $periode,
            'pengurus' => $this->pengurus($unit->id, $periode?->id),
            'anggota' => Member::query()
                ->where('unit_id', $unit->id)
                ->aktif()
                ->orderBy('nama_lengkap')
                ->limit(60)
                ->get(),
            'album' => Gallery::query()
                ->where('unit_id', $unit->id)
                ->publik()
                ->with('item')
                ->urut()
                ->limit(6)
                ->get(),
            'agendaMendatang' => UnitAgenda::query()
                ->where('unit_id', $unit->id)
                ->publik()
                ->mendatang()
                ->limit(6)
                ->get(),
            'agendaLampau' => UnitAgenda::query()
                ->where('unit_id', $unit->id)
                ->publik()
                ->lampau()
                ->limit(6)
                ->get(),
        ]);
    }

    /**
     * Pengurus unit pada periode berjalan.
     *
     * @return \Illuminate\Support\Collection<int, PositionAssignment>
     */
    private function pengurus(int $unitId, ?int $periodeId)
    {
        if ($periodeId === null) {
            return collect();
        }

        return PositionAssignment::query()
            ->where('position_assignments.period_id', $periodeId)
            ->where('position_assignments.aktif', true)
            ->whereHas('jabatan', fn ($q) => $q->where('unit_id', $unitId))
            ->with(['jabatan:id,nama,urutan,unit_id', 'member:id,nama_lengkap,slug'])
            ->join('positions', 'positions.id', '=', 'position_assignments.position_id')
            ->orderBy('positions.urutan')
            ->orderBy('position_assignments.urutan')
            ->select('position_assignments.*')
            ->get();
    }
}
