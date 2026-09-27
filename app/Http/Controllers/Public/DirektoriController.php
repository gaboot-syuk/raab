<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Direktori publik anggota & alumni.
 *
 * PENTING: halaman ini hanya menampilkan kolom yang diizinkan pemilik data
 * (lihat Member::bolehTampil). NIM, telepon, email, dan alamat TIDAK pernah
 * ditampilkan di sini — bukan karena filter tampilan, melainkan karena datanya
 * memang tidak pernah diambil dari basis data.
 */
class DirektoriController extends Controller
{
    public function anggota(Request $request): View
    {
        $saring = [
            'angkatan' => $request->string('angkatan')->toString(),
            'unit' => $request->string('unit')->toString(),
            'fakultas' => $request->string('fakultas')->toString(),
            'prodi' => $request->string('prodi')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $daftar = Member::query()
            ->with(['unit:id,nama'])
            ->aktif()
            ->when($saring['angkatan'] !== '', fn ($q) => $q->where('angkatan', (int) $saring['angkatan']))
            ->when($saring['unit'] !== '', fn ($q) => $q->where('unit_id', (int) $saring['unit']))
            ->when($saring['fakultas'] !== '', fn ($q) => $q->where('fakultas', $saring['fakultas']))
            ->when($saring['prodi'] !== '', fn ($q) => $q->where('program_studi', $saring['prodi']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('nama_lengkap', 'like', "%{$saring['cari']}%")
                    ->orWhere('program_studi', 'like', "%{$saring['cari']}%")
                    ->orWhere('nim', 'like', "%{$saring['cari']}%"),
            ))
            ->orderBy('nama_lengkap')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Member $anggota): array => $anggota->untukDirektori());

        return view('public.anggota', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'saring' => $saring,
            'daftarAngkatan' => $this->daftarAngkatan(Member::STATUS_AKTIF),
            'daftarUnit' => OrganisationUnit::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get(['id', 'nama', 'jenis']),
            'daftarFakultas' => $this->daftarKolom('fakultas', Member::STATUS_AKTIF),
            'daftarProdi' => $this->daftarKolom('program_studi', Member::STATUS_AKTIF),
        ]);
    }

    public function alumni(Request $request): View
    {
        $saring = [
            'tahun_lulus' => $request->string('tahun_lulus')->toString(),
            'bidang' => $request->string('bidang')->toString(),
            'domisili' => trim($request->string('domisili')->toString()),
            'instansi' => trim($request->string('instansi')->toString()),
            'mentor' => $request->string('mentor')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $daftar = Member::query()
            ->with(['unit:id,nama', 'profilAlumni'])
            ->alumni()
            ->when($saring['cari'] !== '', fn ($q) => $q->where('nama_lengkap', 'like', "%{$saring['cari']}%"))
            // Saringan yang menyentuh tabel profil alumni memakai whereHas agar
            // alumni tanpa profil tidak menghilang dari daftar tanpa saringan.
            ->when($saring['tahun_lulus'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'tahun_lulus', (int) $saring['tahun_lulus']))
            ->when($saring['bidang'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'bidang', $saring['bidang']))
            ->when($saring['domisili'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'kota_domisili', 'like', "%{$saring['domisili']}%"))
            ->when($saring['instansi'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'instansi', 'like', "%{$saring['instansi']}%"))
            ->when($saring['mentor'] === '1', fn ($q) => $q->whereRelation('profilAlumni', 'bersedia_mentor', true))
            ->orderBy('nama_lengkap')
            ->paginate(24)
            ->withQueryString()
            ->through(function (Member $anggota): array {
                $profil = $anggota->profilAlumni;

                /*
                 * Untuk alumni, yang menentukan adalah pilihan pemiliknya di
                 * `alumni_profiles.kontak_publik` — bukan `members.privasi`.
                 * Sebelumnya keduanya tercampur sehingga mematikan/menyalakan
                 * satu sakelar tidak berpengaruh pada direktori. Bila profil
                 * alumni belum ada, barulah `members.privasi` dipakai.
                 */
                $boleh = fn (string $bagian): bool => $profil?->bolehTampil($bagian)
                    ?? $anggota->bolehTampil($bagian);

                return [
                    ...$anggota->untukDirektori(),
                    'tahun_lulus' => $profil?->tahun_lulus,
                    'bidang' => $profil?->bidang,
                    'bersedia_mentor' => (bool) $profil?->bersedia_mentor,
                    'topik_mentor' => $profil?->topik_mentor,
                    // Instansi & jabatan pun tunduk pada izin pemilik data.
                    'instansi' => $boleh('instansi') ? $profil?->instansi : null,
                    'jabatan' => $boleh('instansi') ? $profil?->jabatan : null,
                    'telepon' => $boleh('telepon') ? $anggota->telepon : null,
                    'email' => $boleh('email') ? $anggota->email_kontak : null,
                ];
            });

        return view('public.alumni', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'saring' => $saring,
            'daftarTahunLulus' => AlumniProfile::query()
                ->whereNotNull('tahun_lulus')
                ->distinct()
                ->orderByDesc('tahun_lulus')
                ->pluck('tahun_lulus'),
            'daftarBidang' => AlumniProfile::query()
                ->whereNotNull('bidang')
                ->distinct()
                ->orderBy('bidang')
                ->pluck('bidang'),
            'jumlahMentor' => AlumniProfile::query()->where('bersedia_mentor', true)->count(),
            // Titik peta sebaran. Hanya alumni yang MENGISI koordinatnya sendiri
            // (atau yang diberi izin oleh pengurus) yang muncul di peta.
            'peta' => AlumniProfile::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with(['member:id,nama_lengkap,slug,status', 'member.unit:id,nama'])
                ->whereHas('member', fn ($q) => $q->where('status', Member::STATUS_ALUMNI))
                ->get()
                ->filter(fn (AlumniProfile $profil): bool => $profil->member !== null)
                ->map(fn (AlumniProfile $profil): array => [
                    'nama' => $profil->member->nama_lengkap,
                    'slug' => $profil->member->slug,
                    'lat' => (float) $profil->latitude,
                    'lng' => (float) $profil->longitude,
                    'kota' => $profil->kota_domisili,
                    'tahun_lulus' => $profil->tahun_lulus,
                    // Instansi hanya ikut bila alumni mengizinkannya.
                    'instansi' => $profil->bolehTampil('instansi') ? $profil->instansi : null,
                ])
                ->values(),
        ]);
    }

    /**
     * Daftar nilai unik sebuah kolom anggota, untuk mengisi pilihan saringan.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function daftarKolom(string $kolom, string $status)
    {
        return Member::query()
            ->where('status', $status)
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->distinct()
            ->orderBy($kolom)
            ->pluck($kolom);
    }

    /**
     * Daftar tahun angkatan yang benar-benar ada, untuk mengisi pilihan saringan.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function daftarAngkatan(string $status)
    {
        return Member::query()
            ->where('status', $status)
            ->whereNotNull('angkatan')
            ->distinct()
            ->orderByDesc('angkatan')
            ->pluck('angkatan');
    }
}
