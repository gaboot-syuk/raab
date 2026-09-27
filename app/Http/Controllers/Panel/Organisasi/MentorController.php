<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar alumni yang bersedia menjadi mentor / pemateri.
 *
 * Hanya untuk pengurus (Sekretaris memakai izin `mentors.view`). Alumni yang
 * menandai kesediaannya sendiri yang muncul di sini — bukan hasil penunjukan.
 */
class MentorController extends Controller
{
    public function index(Request $request): Response
    {
        $cari = trim($request->string('cari')->toString());
        $topik = trim($request->string('topik')->toString());

        $daftar = AlumniProfile::query()
            ->where('bersedia_mentor', true)
            ->with(['member:id,nama_lengkap,slug,nomor_anggota,unit_id,status', 'member.unit:id,nama'])
            ->whereHas('member')
            ->when($cari !== '', fn ($q) => $q->whereHas('member', fn ($qq) => $qq->where('nama_lengkap', 'like', "%{$cari}%")))
            ->when($topik !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('topik_mentor', 'like', "%{$topik}%")
                    ->orWhere('bidang', 'like', "%{$topik}%"),
            ))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (AlumniProfile $profil): array => [
                'id' => $profil->id,
                'nama' => $profil->member?->nama_lengkap,
                'slug' => $profil->member?->slug,
                'nomor_anggota' => $profil->member?->nomor_anggota,
                'unit' => $profil->member?->unit?->nama,
                'tahun_lulus' => $profil->tahun_lulus,
                'instansi' => $profil->instansi,
                'jabatan' => $profil->jabatan,
                'bidang' => $profil->bidang,
                'kota_domisili' => $profil->kota_domisili,
                'topik_mentor' => $profil->topik_mentor,
                // Kontak ditampilkan kepada pengurus saja; di direktori publik
                // hanya muncul bila alumni mengizinkannya.
                'telepon' => $profil->member?->telepon,
                'email' => $profil->member?->email_kontak,
            ]);

        return Inertia::render('Panel/Organisasi/Mentor', [
            'daftar' => $daftar,
            'saring' => ['cari' => $cari, 'topik' => $topik],
            'jumlah' => $daftar->count(),
        ]);
    }
}
