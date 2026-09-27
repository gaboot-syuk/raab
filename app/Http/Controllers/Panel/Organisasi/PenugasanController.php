<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Period;
use App\Models\Position;
use App\Models\PositionAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Susunan pengurus per periode.
 *
 * Pengurus TIDAK HARUS anggota terdaftar: dosen pembina, tokoh, atau kader yang
 * datanya belum masuk dapat ditunjuk dengan `nama_manual`. Karena itu validasi
 * menuntut salah satu dari `member_id` atau `nama_manual` terisi — bukan
 * keduanya boleh kosong.
 */
class PenugasanController extends Controller
{
    public function index(Request $request): Response
    {
        $periodeId = $request->integer('periode');

        $periode = $periodeId > 0
            ? Period::query()->find($periodeId)
            : Period::sedangAktif();

        // Bila belum ada periode aktif sama sekali, pakai periode terbaru agar
        // halaman tetap dapat dibuka (bukan layar kosong tanpa penjelasan).
        $periode ??= Period::query()->orderByDesc('tahun_selesai')->first();

        $penugasan = $periode
            ? PositionAssignment::query()
                ->where('period_id', $periode->id)
                ->with(['jabatan:id,nama,level,urutan,unit_id', 'jabatan.unit:id,nama,singkatan', 'member:id,nama_lengkap,nomor_anggota,unit_id'])
                ->orderBy('urutan')
                ->orderBy('id')
                ->get()
                ->map(fn (PositionAssignment $item): array => [
                    'id' => $item->id,
                    'position_id' => $item->position_id,
                    'jabatan' => $item->jabatan?->nama,
                    'level' => $item->jabatan?->level,
                    'unit' => $item->jabatan?->unit?->nama,
                    'member_id' => $item->member_id,
                    'nama_manual' => $item->nama_manual,
                    'nama' => $item->namaTampil(),
                    'nomor_anggota' => $item->member?->nomor_anggota,
                    'keterangan' => $item->keterangan,
                    'urutan' => $item->urutan,
                    'aktif' => $item->aktif,
                ])
            : collect();

        return Inertia::render('Panel/Organisasi/Penugasan', [
            'periode' => $periode
                ? [
                    'id' => $periode->id,
                    'nama' => $periode->nama,
                    'aktif' => $periode->aktif,
                ]
                : null,
            'daftarPeriode' => Period::query()
                ->orderByDesc('tahun_selesai')
                ->get(['id', 'nama', 'aktif'])
                ->map(fn (Period $p): array => ['id' => $p->id, 'nama' => $p->nama, 'aktif' => $p->aktif]),
            'penugasan' => $penugasan,
            'daftarJabatan' => Position::query()
                ->where('aktif', true)
                ->with('unit:id,nama')
                ->orderBy('level')
                ->orderBy('urutan')
                ->get()
                ->map(fn (Position $jabatan): array => [
                    'id' => $jabatan->id,
                    'nama' => $jabatan->unit ? $jabatan->nama : $jabatan->nama,
                    'label' => $jabatan->nama.($jabatan->unit ? ' ('.$jabatan->unit->nama.')' : ''),
                ]),
            'pilihanAnggota' => $this->pilihanAnggota(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $penugasan = new PositionAssignment;
        $penugasan->fill($data);
        $penugasan->save();

        $this->catat($penugasan, 'Penugasan pengurus ditambahkan');

        return back()->with('sukses', 'Penugasan untuk '.$penugasan->namaTampil().' disimpan.');
    }

    public function perbarui(Request $request, PositionAssignment $penugasan): RedirectResponse
    {
        $data = $this->validasi($request);

        $penugasan->fill($data);
        $penugasan->save();

        return back()->with('sukses', 'Penugasan diperbarui.');
    }

    public function hapus(PositionAssignment $penugasan): RedirectResponse
    {
        $nama = $penugasan->namaTampil();
        $penugasan->delete();

        return back()->with('sukses', 'Penugasan '.$nama.' dihapus dari periode ini.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'nama_manual' => ['nullable', 'string', 'max:160'],
            'keterangan' => ['nullable', 'string', 'max:190'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ]);

        if (blank($data['member_id'] ?? null) && blank($data['nama_manual'] ?? null)) {
            throw ValidationException::withMessages([
                'nama_manual' => 'Pilih anggota terdaftar, atau isi nama manual bila yang ditunjuk bukan anggota.',
            ]);
        }

        $data['member_id'] = $data['member_id'] ?? null;
        $data['nama_manual'] = $data['nama_manual'] ?? null;
        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = (bool) ($data['aktif'] ?? true);

        return $data;
    }

    private function catat(PositionAssignment $penugasan, string $pesan): void
    {
        $jabatan = Position::query()->find($penugasan->position_id);

        activity()
            ->performedOn($penugasan)
            ->withProperties([
                'periode_id' => $penugasan->period_id,
                'jabatan' => $jabatan?->nama,
                'nama' => $penugasan->namaTampil(),
            ])
            ->log($pesan);
    }

    /**
     * Daftar anggota terverifikasi untuk pemilih pengurus.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pilihanAnggota()
    {
        return Member::query()
            ->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])
            ->orderBy('nama_lengkap')
            ->limit(500)
            ->get(['id', 'nama_lengkap', 'nomor_anggota', 'status'])
            ->map(fn (Member $anggota): array => [
                'id' => $anggota->id,
                'nama' => $anggota->nama_lengkap,
                'nomor_anggota' => $anggota->nomor_anggota,
                'label' => $anggota->nama_lengkap.($anggota->nomor_anggota ? ' — '.$anggota->nomor_anggota : ''),
            ]);
    }
}
