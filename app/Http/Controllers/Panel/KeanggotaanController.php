<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Services\Keanggotaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengelolaan keanggotaan (Sekretaris).
 *
 * Data sensitif (NIM, telepon, email) hanya dikirim ke panel bila pengguna
 * memegang izin members.view-sensitive — sehingga peran lain tidak dapat
 * melihatnya sekalipun membuka halaman ini.
 */
class KeanggotaanController extends Controller
{
    public function __construct(
        private readonly Keanggotaan $keanggotaan,
    ) {}

    public function index(Request $request): Response
    {
        $saring = [
            'status' => $request->string('status')->toString() ?: 'semua',
            'jalur' => $request->string('jalur')->toString() ?: 'semua',
            'angkatan' => $request->string('angkatan')->toString(),
            'unit' => $request->string('unit')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $bolehSensitif = $request->user()?->can('members.view-sensitive') ?? false;

        $daftar = Member::query()
            ->with(['unit:id,nama', 'kartu:id,member_id,nomor_kartu,status'])
            ->when($saring['status'] !== 'semua', fn ($q) => $q->where('status', $saring['status']))
            ->when($saring['jalur'] !== 'semua', fn ($q) => $q->where('jalur', $saring['jalur']))
            ->when($saring['angkatan'] !== '', fn ($q) => $q->where('angkatan', (int) $saring['angkatan']))
            ->when($saring['unit'] !== '', fn ($q) => $q->where('unit_id', (int) $saring['unit']))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('nama_lengkap', 'like', "%{$saring['cari']}%")
                    ->orWhere('nomor_anggota', 'like', "%{$saring['cari']}%")
                    ->orWhere('nim', 'like', "%{$saring['cari']}%"),
            ))
            ->orderBy('nama_lengkap')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Member $anggota): array => [
                'id' => $anggota->id,
                'nomor_anggota' => $anggota->nomor_anggota,
                'nama' => $anggota->nama_lengkap,
                'jalur' => $anggota->jalur,
                'label_jalur' => $anggota->labelJalur(),
                'status' => $anggota->status,
                'label_status' => $anggota->labelStatus(),
                'angkatan' => $anggota->angkatan,
                'fakultas' => $anggota->fakultas,
                'program_studi' => $anggota->program_studi,
                'unit' => $anggota->unit?->nama,
                'nim' => $bolehSensitif ? $anggota->nim : null,
                'telepon' => $bolehSensitif ? $anggota->telepon : null,
                'punya_kartu' => (bool) $anggota->kartu,
                'kartu_aktif' => $anggota->kartu?->status === 'aktif',
                'kelengkapan' => $anggota->kelengkapanProfil(),
            ]);

        return Inertia::render('Panel/Keanggotaan/Index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'bolehSensitif' => $bolehSensitif,
            'jumlah' => [
                'semua' => Member::query()->count(),
                'aktif' => Member::query()->aktif()->count(),
                'alumni' => Member::query()->alumni()->count(),
                'menunggu' => Member::query()->where('status', Member::STATUS_MENUNGGU)->count(),
                'nonaktif' => Member::query()->where('status', Member::STATUS_NONAKTIF)->count(),
            ],
            'pilihanStatus' => Member::STATUS,
            'pilihanJalur' => Member::JALUR,
            'daftarAngkatan' => Member::query()
                ->whereNotNull('angkatan')
                ->distinct()
                ->orderByDesc('angkatan')
                ->pluck('angkatan'),
            'daftarUnit' => OrganisationUnit::query()->aktif()->orderBy('jenis')->orderBy('urutan')->get(['id', 'nama', 'jenis']),
        ]);
    }

    public function detail(Request $request, Member $anggota): Response
    {
        $anggota->load(['user:id,name,email', 'unit:id,nama', 'kartu', 'profilAlumni', 'riwayatStatus.pengubah:id,name']);

        $bolehSensitif = $request->user()?->can('members.view-sensitive') ?? false;

        return Inertia::render('Panel/Keanggotaan/Detail', [
            'anggota' => [
                'id' => $anggota->id,
                'nomor_anggota' => $anggota->nomor_anggota,
                'nama' => $anggota->nama_lengkap,
                'nama_panggilan' => $anggota->nama_panggilan,
                'status' => $anggota->status,
                'label_status' => $anggota->labelStatus(),
                'jalur' => $anggota->jalur,
                'label_jalur' => $anggota->labelJalur(),
                'jenis_kelamin' => $anggota->jenis_kelamin,
                'tempat_lahir' => $anggota->tempat_lahir,
                'tanggal_lahir' => $anggota->tanggal_lahir?->translatedFormat('d F Y'),
                'angkatan' => $anggota->angkatan,
                'fakultas' => $anggota->fakultas,
                'program_studi' => $anggota->program_studi,
                'unit' => $anggota->unit?->nama,
                'keahlian' => $anggota->keahlian ?? [],
                'kelengkapan' => $anggota->kelengkapanProfil(),
                'akun' => [
                    'nama' => $anggota->user?->name,
                    'email' => $anggota->user?->email,
                    'terverifikasi' => (bool) $anggota->user?->hasVerifiedEmail(),
                ],
                'sensitif' => $bolehSensitif ? [
                    'nim' => $anggota->nim,
                    'telepon' => $anggota->telepon,
                    'email_kontak' => $anggota->email_kontak,
                    'alamat' => $anggota->alamat,
                ] : null,
                'kartu' => $anggota->kartu ? [
                    'nomor_kartu' => $anggota->kartu->nomor_kartu,
                    'status' => $anggota->kartu->status,
                    'berlaku_sampai' => $anggota->kartu->berlaku_sampai?->translatedFormat('d M Y'),
                    'diterbitkan_pada' => $anggota->kartu->diterbitkan_pada?->translatedFormat('d M Y'),
                    'alasan_pencabutan' => $anggota->kartu->alasan_pencabutan,
                ] : null,
                'alumni' => $anggota->profilAlumni ? [
                    'tahun_lulus' => $anggota->profilAlumni->tahun_lulus,
                    'instansi' => $anggota->profilAlumni->instansi,
                    'jabatan' => $anggota->profilAlumni->jabatan,
                    'bidang' => $anggota->profilAlumni->bidang,
                    'kota_domisili' => $anggota->profilAlumni->kota_domisili,
                    'bersedia_mentor' => $anggota->profilAlumni->bersedia_mentor,
                ] : null,
                'riwayat' => $anggota->riwayatStatus->map(fn ($baris): array => [
                    'status_lama' => $baris->status_lama,
                    'status_baru' => $baris->status_baru,
                    'alasan' => $baris->alasan,
                    'oleh' => $baris->pengubah?->name ?? 'Sistem',
                    'waktu' => $baris->created_at?->translatedFormat('d M Y H:i'),
                ])->all(),
            ],
            'pilihanStatus' => Member::STATUS,
            'bolehSensitif' => $bolehSensitif,
        ]);
    }

    public function ubahStatus(Request $request, Member $anggota): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Member::STATUS))],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'status' => 'status keanggotaan',
            'alasan' => 'alasan perubahan',
        ]);

        $statusLama = $anggota->labelStatus();
        $this->keanggotaan->ubahStatus($anggota, $data['status'], $data['alasan'] ?? null, $request->user());

        return back()->with(
            'sukses',
            'Status '.$anggota->nama_lengkap.' diubah dari '.$statusLama.' menjadi '.$anggota->fresh()->labelStatus().'.',
        );
    }

    /**
     * Naikkan beberapa kader menjadi alumni sekaligus.
     */
    public function massalAlumni(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['integer', 'exists:members,id'],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'anggota' => 'pilihan anggota',
        ]);

        $daftar = Member::query()->whereIn('id', $data['anggota'])->get();

        $jumlah = $this->keanggotaan->jadikanAlumniMassal($daftar, $request->user(), $data['alasan'] ?? null);

        return back()->with(
            $jumlah > 0 ? 'sukses' : 'galat',
            $jumlah > 0
                ? $jumlah.' kader berhasil dipindahkan ke direktori alumni.'
                : 'Tidak ada kader aktif pada pilihan tersebut.',
        );
    }

    /**
     * Terbitkan ulang kartu kader (mis. setelah masa berlaku habis).
     */
    public function terbitkanKartu(Request $request, Member $anggota): RedirectResponse
    {
        abort_unless($request->user()?->can('member-cards.issue'), 403);

        if (! in_array($anggota->status, [Member::STATUS_AKTIF], true)) {
            return back()->with('galat', 'Kartu hanya dapat diterbitkan untuk kader berstatus aktif.');
        }

        $this->keanggotaan->terbitkanKartu($anggota);

        return back()->with('sukses', 'Kartu kader '.$anggota->nama_lengkap.' diterbitkan ulang.');
    }
}
