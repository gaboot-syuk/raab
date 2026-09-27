<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\OrganisationUnit;
use App\Services\Keanggotaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Antrean verifikasi pendaftar (Sekretaris).
 *
 * Alur: pendaftar mengirim formulir → muncul di sini berstatus "menunggu" →
 * Sekretaris memilih salah satu dari tiga jalan (setujui / minta perbaikan /
 * tolak), seluruhnya wajib disertai catatan kecuali persetujuan.
 *
 * Sekretaris TIDAK dapat memverifikasi pengajuannya sendiri — aturan ini
 * menjaga agar tidak ada yang meloloskan dirinya menjadi anggota.
 */
class VerifikasiController extends Controller
{
    public function __construct(
        private readonly Keanggotaan $keanggotaan,
    ) {}

    public function index(Request $request): Response
    {
        $saring = $request->string('status')->toString() ?: MemberApplication::STATUS_MENUNGGU;
        $cari = trim($request->string('cari')->toString());

        $daftar = MemberApplication::query()
            ->with(['user:id,name,email', 'member:id,nama_lengkap,nomor_anggota,status'])
            ->when(
                $saring !== 'semua',
                fn ($q) => $q->where('status', $saring),
            )
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->whereRelation('user', 'name', 'like', "%{$cari}%")
                    ->orWhereRelation('user', 'email', 'like', "%{$cari}%")
                    ->orWhere('data->nim', 'like', "%{$cari}%")
                    ->orWhere('data->nama_lengkap', 'like', "%{$cari}%"),
            ))
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MemberApplication $pengajuan): array => $this->ringkas($pengajuan));

        return Inertia::render('Panel/Verifikasi/Index', [
            'daftar' => $daftar,
            'saring' => $saring,
            'cari' => $cari,
            'jumlah' => [
                'menunggu' => MemberApplication::query()->menunggu()->count(),
                'perbaikan' => MemberApplication::query()->where('status', MemberApplication::STATUS_PERBAIKAN)->count(),
                'disetujui' => MemberApplication::query()->where('status', MemberApplication::STATUS_DISETUJUI)->count(),
                'ditolak' => MemberApplication::query()->where('status', MemberApplication::STATUS_DITOLAK)->count(),
            ],
            'pilihanStatus' => MemberApplication::STATUS,
        ]);
    }

    public function detail(Request $request, MemberApplication $pengajuan): Response
    {
        $pengajuan->load(['user', 'member.unit', 'pemroses:id,name']);

        return Inertia::render('Panel/Verifikasi/Detail', [
            'pengajuan' => [
                ...$this->ringkas($pengajuan),
                'data' => $pengajuan->data,
                'catatan_pengurus' => $pengajuan->catatan_pengurus,
                'catatan_internal' => $pengajuan->catatan_internal,
                'diproses_oleh' => $pengajuan->pemroses?->name,
                'diproses_pada' => $pengajuan->diproses_pada?->translatedFormat('d M Y H:i'),
                'member' => $pengajuan->member ? [
                    'id' => $pengajuan->member->id,
                    'nomor_anggota' => $pengajuan->member->nomor_anggota,
                    'status' => $pengajuan->member->status,
                    'label_status' => $pengajuan->member->labelStatus(),
                    'unit' => $pengajuan->member->unit?->nama,
                ] : null,
            ],
            'pilihanStatus' => MemberApplication::STATUS,
            'bolehMemproses' => $this->bolehMemproses($request, $pengajuan),
        ]);
    }

    public function setujui(Request $request, MemberApplication $pengajuan): RedirectResponse
    {
        $this->pastikanBolehMemproses($request, $pengajuan);

        $data = $request->validate([
            'catatan_internal' => ['nullable', 'string', 'max:2000'],
        ]);

        $pengajuan->forceFill(['catatan_internal' => $data['catatan_internal'] ?? null])->save();

        $anggota = $this->keanggotaan->setujui($pengajuan, $request->user());

        return redirect()
            ->route('panel.verifikasi')
            ->with('sukses', 'Pengajuan '.$anggota->nama_lengkap.' disetujui dengan nomor anggota '.$anggota->nomor_anggota.'.');
    }

    public function tolak(Request $request, MemberApplication $pengajuan): RedirectResponse
    {
        $this->pastikanBolehMemproses($request, $pengajuan);

        $data = $request->validate([
            'catatan_pengurus' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'catatan_pengurus.required' => 'Alasan penolakan wajib diisi agar pemohon memahami penyebabnya.',
            'catatan_pengurus.min' => 'Alasan penolakan terlalu pendek — tuliskan minimal :min karakter.',
        ], [
            'catatan_pengurus' => 'alasan penolakan',
        ]);

        $this->keanggotaan->tolak($pengajuan, $request->user(), $data['catatan_pengurus']);

        return redirect()
            ->route('panel.verifikasi')
            ->with('sukses', 'Pengajuan '.$pengajuan->user->name.' ditolak dan pemohon sudah diberi kabar.');
    }

    public function mintaPerbaikan(Request $request, MemberApplication $pengajuan): RedirectResponse
    {
        $this->pastikanBolehMemproses($request, $pengajuan);

        $data = $request->validate([
            'catatan_pengurus' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'catatan_pengurus.required' => 'Sebutkan bagian mana yang perlu diperbaiki.',
        ], [
            'catatan_pengurus' => 'catatan perbaikan',
        ]);

        $this->keanggotaan->mintaPerbaikan($pengajuan, $request->user(), $data['catatan_pengurus']);

        return redirect()
            ->route('panel.verifikasi')
            ->with('sukses', 'Permintaan perbaikan dikirim ke '.$pengajuan->user->name.'.');
    }

    /**
     * Sekretaris tidak boleh memverifikasi pengajuannya sendiri.
     */
    private function bolehMemproses(Request $request, MemberApplication $pengajuan): bool
    {
        return $pengajuan->user_id !== $request->user()?->id
            && $pengajuan->status === MemberApplication::STATUS_MENUNGGU;
    }

    private function pastikanBolehMemproses(Request $request, MemberApplication $pengajuan): void
    {
        abort_if(
            $pengajuan->user_id === $request->user()?->id,
            403,
            'Kamu tidak dapat memverifikasi pengajuanmu sendiri.',
        );

        abort_if(
            $pengajuan->status !== MemberApplication::STATUS_MENUNGGU,
            403,
            'Pengajuan ini sudah diproses sebelumnya.',
        );
    }

    /**
     * Ringkasan satu pengajuan untuk daftar & halaman detail.
     *
     * @return array<string, mixed>
     */
    private function ringkas(MemberApplication $pengajuan): array
    {
        $unit = $pengajuan->isian('unit_id')
            ? OrganisationUnit::query()->find($pengajuan->isian('unit_id'))?->nama
            : null;

        return [
            'id' => $pengajuan->id,
            'jalur' => $pengajuan->jalur,
            'label_jalur' => Member::JALUR[$pengajuan->jalur] ?? $pengajuan->jalur,
            'status' => $pengajuan->status,
            'label_status' => $pengajuan->labelStatus(),
            'nama' => $pengajuan->isian('nama_lengkap') ?? $pengajuan->user?->name,
            'email' => $pengajuan->user?->email,
            'telepon' => $pengajuan->isian('telepon'),
            'nim' => $pengajuan->isian('nim'),
            'fakultas' => $pengajuan->isian('fakultas'),
            'program_studi' => $pengajuan->isian('program_studi'),
            'angkatan' => $pengajuan->isian('angkatan'),
            'tahun_lulus' => $pengajuan->isian('tahun_lulus'),
            'instansi' => $pengajuan->isian('instansi'),
            'unit' => $unit,
            'dikirim_pada' => $pengajuan->created_at?->translatedFormat('d M Y H:i'),
        ];
    }
}
