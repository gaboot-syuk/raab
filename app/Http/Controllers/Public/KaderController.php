<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Member;
use App\Models\Period;
use App\Support\Pengaturan;
use Illuminate\View\View;

/**
 * Profil publik kader & halaman prestasi.
 *
 * Hanya anggota yang MEMBUKA profilnya (`profil_publik`) yang dapat dibuka.
 * Ini keputusan pemilik data, bukan pengurus — sejalan dengan janji privasi
 * pada halaman profil anggota.
 *
 * Data prestasi resmi menyusul pada Fase 8; yang tampil sekarang adalah
 * riwayat kepengurusan dan karya tulis yang sudah diterbitkan.
 */
class KaderController extends Controller
{
    public function __invoke(string $slug): View
    {
        $kader = Member::query()
            ->profilTerbuka()
            ->slug($slug)
            ->with([
                'unit:id,nama,jenis',
                'profilAlumni',
                'penugasan' => fn ($q) => $q->with('jabatan:id,nama,unit_id', 'jabatan.unit:id,nama', 'periode:id,nama')->orderByDesc('period_id'),
            ])
            ->firstOrFail();

        $periode = Period::query()->orderByDesc('tahun_selesai')->get(['id', 'nama']);

        return view('public.kader', [
            'situs' => Pengaturan::semua(),
            'kader' => $kader,
            'riwayat' => $kader->penugasan->map(fn ($item): array => [
                'jabatan' => $item->jabatan?->nama,
                'unit' => $item->jabatan?->unit?->nama,
                'periode' => $periode->firstWhere('id', $item->period_id)?->nama,
            ]),
            'karya' => $kader->user_id
                ? \App\Models\Article::query()
                    ->where('user_id', $kader->user_id)
                    ->terbit()
                    ->orderByDesc('terbit_pada')
                    ->limit(6)
                    ->get()
                : collect(),

            /*
             * Hanya prestasi yang SUDAH terverifikasi DAN diizinkan pemiliknya.
             * Halaman ini menyiarkan nama kader, jadi keputusan pemilik datanya
             * tidak boleh diabaikan hanya karena pengurus sudah memverifikasi.
             */
            'prestasi' => Achievement::query()
                ->tayangPublik()
                ->where('member_id', $kader->id)
                ->with('kategori:id,nama')
                ->orderByDesc('tanggal')
                ->limit(20)
                ->get(),
        ]);
    }
}
