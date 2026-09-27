<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\OrganisationUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dasbor anggota — dipakai kader aktif maupun alumni.
 *
 * Isi halaman menyesuaikan keadaan: pendaftar yang belum diverifikasi melihat
 * status pengajuannya, kader melihat nomor anggota & kartunya, alumni melihat
 * ringkasan profil alumni.
 *
 * Berbeda dari panel pengurus, area ini TIDAK memerlukan izin khusus: setiap
 * pengguna yang sudah memverifikasi email boleh masuk.
 */
class DasborController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $anggota = $user->member;

        $pengajuan = MemberApplication::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return Inertia::render('Anggota/Dasbor', [
            'anggota' => $anggota ? [
                'id' => $anggota->id,
                'nomor_anggota' => $anggota->nomor_anggota,
                'nama' => $anggota->nama_lengkap,
                'status' => $anggota->status,
                'label_status' => $anggota->labelStatus(),
                'jalur' => $anggota->jalur,
                'label_jalur' => $anggota->labelJalur(),
                'angkatan' => $anggota->angkatan,
                'fakultas' => $anggota->fakultas,
                'program_studi' => $anggota->program_studi,
                'unit' => $anggota->unit?->nama,
                'kelengkapan' => $anggota->kelengkapanProfil(),
                'kartu' => $anggota->kartu && $anggota->kartu->status === 'aktif' ? [
                    'nomor_kartu' => $anggota->kartu->nomor_kartu,
                    'berlaku_sampai' => $anggota->kartu->berlaku_sampai?->translatedFormat('d F Y'),
                ] : null,
                'alumni' => $anggota->profilAlumni ? [
                    'tahun_lulus' => $anggota->profilAlumni->tahun_lulus,
                    'instansi' => $anggota->profilAlumni->instansi,
                    'jabatan' => $anggota->profilAlumni->jabatan,
                    'kota_domisili' => $anggota->profilAlumni->kota_domisili,
                    'bersedia_mentor' => $anggota->profilAlumni->bersedia_mentor,
                ] : null,
            ] : null,
            'pengajuan' => $pengajuan ? [
                'status' => $pengajuan->status,
                'label_status' => $pengajuan->labelStatus(),
                'jalur' => $pengajuan->jalur,
                'catatan_pengurus' => $pengajuan->catatan_pengurus,
                'dikirim_pada' => $pengajuan->created_at?->translatedFormat('d M Y H:i'),
                'diproses_pada' => $pengajuan->diproses_pada?->translatedFormat('d M Y H:i'),
            ] : null,
            'pintasan' => [
                ['label' => 'Tulis Karya Baru', 'tautan' => '/karya/baru'],
                ['label' => 'Karya Saya', 'tautan' => '/karya'],
                ['label' => 'Profil Saya', 'tautan' => '/profil'],
            ],
        ]);
    }

    public function profil(Request $request): Response
    {
        $anggota = $request->user()->member;

        return Inertia::render('Anggota/Profil', [
            'anggota' => $anggota ? [
                'nama_lengkap' => $anggota->nama_lengkap,
                'nama_panggilan' => $anggota->nama_panggilan,
                'jenis_kelamin' => $anggota->jenis_kelamin,
                'tempat_lahir' => $anggota->tempat_lahir,
                'tanggal_lahir' => $anggota->tanggal_lahir?->format('Y-m-d'),
                'nim' => $anggota->nim,
                'fakultas' => $anggota->fakultas,
                'program_studi' => $anggota->program_studi,
                'angkatan' => $anggota->angkatan,
                'alamat' => $anggota->alamat,
                'telepon' => $anggota->telepon,
                'email_kontak' => $anggota->email_kontak,
                'unit_id' => $anggota->unit_id,
                'keahlian' => implode(', ', $anggota->keahlian ?? []),
                'sosmed' => $anggota->sosmed ?? [],
                'privasi' => $anggota->privasi ?? [],
                'profil_publik' => (bool) $anggota->profil_publik,
                'jalur' => $anggota->jalur,
                'status' => $anggota->status,
                'label_status' => $anggota->labelStatus(),
                'nama_akun' => $request->user()->name,
                'email_akun' => $request->user()->email,
                'alumni' => $anggota->profilAlumni ? [
                    'tahun_lulus' => $anggota->profilAlumni->tahun_lulus,
                    'instansi' => $anggota->profilAlumni->instansi,
                    'jabatan' => $anggota->profilAlumni->jabatan,
                    'bidang' => $anggota->profilAlumni->bidang,
                    'kota_domisili' => $anggota->profilAlumni->kota_domisili,
                    'latitude' => $anggota->profilAlumni->latitude,
                    'longitude' => $anggota->profilAlumni->longitude,
                    'bersedia_mentor' => $anggota->profilAlumni->bersedia_mentor,
                    'topik_mentor' => $anggota->profilAlumni->topik_mentor,
                ] : null,
            ] : null,
            'daftarUnit' => OrganisationUnit::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get(['id', 'nama', 'jenis']),
            'kolomPrivasi' => Member::KOLOM_PUBLIK,
        ]);
    }

    public function perbaruiProfil(Request $request): RedirectResponse
    {
        $anggota = $request->user()->member;

        abort_if($anggota === null, 403, 'Profil anggota belum tersedia — pengajuanmu masih menunggu verifikasi.');

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:160'],
            'nama_panggilan' => ['nullable', 'string', 'max:60'],
            'jenis_kelamin' => ['required', Rule::in(['laki_laki', 'perempuan'])],
            'tempat_lahir' => ['nullable', 'string', 'max:120'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'nim' => ['nullable', 'string', 'max:40'],
            'fakultas' => ['nullable', 'string', 'max:160'],
            'program_studi' => ['nullable', 'string', 'max:160'],
            'angkatan' => ['nullable', 'integer', 'min:2000', 'max:'.(int) now()->format('Y')],
            'alamat' => ['nullable', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'email_kontak' => ['nullable', 'email:filter', 'max:190'],
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'keahlian' => ['nullable', 'string', 'max:500'],
            'sosmed' => ['nullable', 'array'],
            'sosmed.*' => ['nullable', 'string', 'max:190'],
            'privasi' => ['nullable', 'array'],
            'privasi.*' => ['boolean'],

            // Membuka halaman profil publik (/prestasi/kader/{slug}).
            'profil_publik' => ['boolean'],

            // Khusus alumni
            'tahun_lulus' => ['nullable', 'integer', 'min:2000', 'max:'.(int) now()->format('Y')],
            'instansi' => ['nullable', 'string', 'max:190'],
            'jabatan' => ['nullable', 'string', 'max:160'],
            'bidang' => ['nullable', 'string', 'max:160'],
            'kota_domisili' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bersedia_mentor' => ['boolean'],
            'topik_mentor' => ['nullable', 'string', 'max:1000'],
        ]);

        $anggota->fill([
            'nama_lengkap' => $data['nama_lengkap'],
            'nama_panggilan' => $data['nama_panggilan'] ?? null,
            'jenis_kelamin' => $data['jenis_kelamin'],
            'tempat_lahir' => $data['tempat_lahir'] ?? null,
            'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
            'nim' => $data['nim'] ?? null,
            'fakultas' => $data['fakultas'] ?? null,
            'program_studi' => $data['program_studi'] ?? null,
            'angkatan' => $data['angkatan'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'telepon' => $data['telepon'] ?? null,
            'email_kontak' => $data['email_kontak'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            // Keahlian ditulis sebagai daftar dipisah koma agar mudah diisi dari
            // satu kolom teks; disimpan sebagai larik.
            'keahlian' => collect(explode(',', (string) ($data['keahlian'] ?? '')))
                ->map(fn (string $butir): string => trim($butir))
                ->filter()
                ->values()
                ->all(),
            'sosmed' => array_filter($data['sosmed'] ?? [], fn ($nilai) => filled($nilai)),
            'privasi' => $data['privasi'] ?? [],
            'profil_publik' => (bool) ($data['profil_publik'] ?? false),
        ]);

        $anggota->save();

        if ($anggota->jalur === Member::JALUR_ALUMNI && $anggota->profilAlumni) {
            $anggota->profilAlumni->update([
                'tahun_lulus' => $data['tahun_lulus'] ?? null,
                'instansi' => $data['instansi'] ?? null,
                'jabatan' => $data['jabatan'] ?? null,
                'bidang' => $data['bidang'] ?? null,
                'kota_domisili' => $data['kota_domisili'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'bersedia_mentor' => (bool) ($data['bersedia_mentor'] ?? false),
                'topik_mentor' => $data['topik_mentor'] ?? null,
                /*
                 * Direktori ALUMNI membaca `kontak_publik`, sedangkan direktori
                 * kader membaca `members.privasi`. Alih-alih meminta alumni
                 * mengurus dua sakelar yang bisa saling bertentangan, keduanya
                 * diisi dari panel privasi yang sama.
                 */
                'kontak_publik' => $data['privasi'] ?? [],
            ]);
        }

        return back()->with('sukses', 'Profil kamu berhasil diperbarui.');
    }
}
