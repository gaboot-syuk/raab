<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\OrganisationUnit;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jabatan dalam kepengurusan.
 *
 * `level` menentukan kedalaman pada bagan struktur (1 = pimpinan, 2 = pengurus,
 * 3 = kepala biro/LSO) dan `urutan` menentukan urutan dalam satu tingkat.
 * Keduanya dipakai halaman /struktur untuk menggambar bagan.
 */
class JabatanController extends Controller
{
    /**
     * @var array<int, string>
     */
    public const LEVEL = [
        1 => 'Pimpinan (Ketua/Wakil)',
        2 => 'Pengurus Harian',
        3 => 'Kepala Biro & LSO',
    ];

    public function index(): Response
    {
        $daftar = Position::query()
            ->with('unit:id,nama,jenis,singkatan')
            ->withCount('penugasan')
            ->orderBy('level')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->map(fn (Position $jabatan): array => [
                'id' => $jabatan->id,
                'nama' => $jabatan->nama,
                'level' => $jabatan->level,
                'urutan' => $jabatan->urutan,
                'unit_id' => $jabatan->unit_id,
                'unit' => $jabatan->unit?->nama,
                'rangkap_diizinkan' => $jabatan->rangkap_diizinkan,
                'aktif' => $jabatan->aktif,
                'jumlah_penugasan' => $jabatan->penugasan_count,
            ]);

        return Inertia::render('Panel/Organisasi/Jabatan', [
            'daftar' => $daftar,
            'level' => self::LEVEL,
            'unit' => OrganisationUnit::query()
                ->orderBy('jenis')
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(['id', 'nama', 'jenis', 'singkatan'])
                ->map(fn (OrganisationUnit $unit): array => [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'label' => $unit->labelJenis().' — '.$unit->nama,
                ]),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $jabatan = new Position;
        $jabatan->fill($this->validasi($request));
        $jabatan->save();

        return back()->with('sukses', 'Jabatan "'.$jabatan->nama.'" ditambahkan.');
    }

    public function perbarui(Request $request, Position $jabatan): RedirectResponse
    {
        $jabatan->fill($this->validasi($request, $jabatan));
        $jabatan->save();

        return back()->with('sukses', 'Jabatan "'.$jabatan->nama.'" diperbarui.');
    }

    public function hapus(Position $jabatan): RedirectResponse
    {
        if ($jabatan->penugasan()->exists()) {
            return back()->with(
                'galat',
                'Jabatan "'.$jabatan->nama.'" masih dipakai pada penugasan pengurus. Nonaktifkan saja agar riwayat lama tetap utuh.',
            );
        }

        $nama = $jabatan->nama;
        $jabatan->delete();

        return back()->with('sukses', 'Jabatan "'.$nama.'" dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Position $jabatan = null): array
    {
        $data = $request->validate([
            'nama' => [
                'required', 'string', 'max:140',
                // Nama jabatan unik per unit (kolom unit_id boleh kosong).
                Rule::unique('positions', 'nama')
                    ->where(fn ($q) => $q->where('unit_id', $request->input('unit_id') ?: null))
                    ->ignore($jabatan?->id),
            ],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'rangkap_diizinkan' => ['boolean'],
            'aktif' => ['boolean'],
        ], [
            'nama.unique' => 'Jabatan dengan nama itu sudah ada pada unit yang sama.',
        ]);

        $data['unit_id'] = $data['unit_id'] ?? null;
        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = (bool) ($data['aktif'] ?? true);
        $data['rangkap_diizinkan'] = (bool) ($data['rangkap_diizinkan'] ?? false);

        return $data;
    }
}
