<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\OrganisationUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Biro (8) dan Lembaga Semi Otonom (5).
 *
 * Menonaktifkan unit TIDAK menghapusnya: anggota yang pernah bernaung di sana
 * tetap menyimpan riwayatnya. Unit yang masih punya jabatan atau anggota tidak
 * dapat dihapus — pesannya menjelaskan alasannya, bukan sekadar menolak.
 */
class UnitController extends Controller
{
    public function index(): Response
    {
        $daftar = OrganisationUnit::query()
            ->withCount(['jabatan', 'anggota', 'galeri'])
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->map(fn (OrganisationUnit $unit): array => [
                'id' => $unit->id,
                'jenis' => $unit->jenis,
                'label_jenis' => $unit->labelJenis(),
                'nama' => $unit->nama,
                'slug' => $unit->slug,
                'singkatan' => $unit->singkatan,
                'deskripsi' => $unit->getTranslations('deskripsi'),
                'warna' => $unit->warna,
                'urutan' => $unit->urutan,
                'aktif' => $unit->aktif,
                'jumlah_jabatan' => $unit->jabatan_count,
                'jumlah_anggota' => $unit->anggota_count,
                'jumlah_galeri' => $unit->galeri_count,
                'tautan_publik' => $unit->jenis === OrganisationUnit::JENIS_LSO
                    ? '/lso/'.$unit->slug
                    : null,
            ]);

        return Inertia::render('Panel/Organisasi/Unit', [
            'daftar' => $daftar,
            'jenis' => OrganisationUnit::JENIS,
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $unit = new OrganisationUnit;
        $this->isi($unit, $request);
        $unit->slug = $this->slugUnik($request->input('nama'), null);
        $unit->save();

        return back()->with('sukses', $unit->labelJenis().' '.$unit->nama.' ditambahkan.');
    }

    public function perbarui(Request $request, OrganisationUnit $unit): RedirectResponse
    {
        $namaLama = $unit->nama;
        $this->isi($unit, $request);

        // Slug lama dipertahankan agar tautan yang sudah dibagikan tidak mati.
        if (blank($unit->slug)) {
            $unit->slug = $this->slugUnik($unit->nama, $unit->id);
        }

        $unit->save();

        return back()->with('sukses', $namaLama === $unit->nama
            ? 'Unit '.$unit->nama.' diperbarui.'
            : 'Unit '.$namaLama.' diperbarui menjadi '.$unit->nama.'.');
    }

    public function hapus(OrganisationUnit $unit): RedirectResponse
    {
        $alasan = match (true) {
            $unit->jabatan()->exists() => 'masih memiliki jabatan',
            $unit->anggota()->exists() => 'masih menjadi unit '.$unit->anggota()->count().' anggota',
            $unit->galeri()->exists() => 'masih memiliki album galeri',
            default => null,
        };

        if ($alasan !== null) {
            return back()->with(
                'galat',
                'Unit '.$unit->nama.' tidak dapat dihapus karena '.$alasan.'. Nonaktifkan saja agar data lama tetap utuh.',
            );
        }

        $nama = $unit->nama;
        $unit->delete();

        return back()->with('sukses', 'Unit '.$nama.' dihapus.');
    }

    private function isi(OrganisationUnit $unit, Request $request): void
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(OrganisationUnit::JENIS))],
            'nama' => [
                'required', 'string', 'max:120',
                Rule::unique('organisation_units', 'nama')
                    ->where(fn ($q) => $q->where('jenis', $request->input('jenis')))
                    ->ignore($unit->id),
            ],
            'singkatan' => ['nullable', 'string', 'max:40'],
            'deskripsi' => ['nullable', 'array'],
            'deskripsi.id' => ['nullable', 'string', 'max:2000'],
            'deskripsi.en' => ['nullable', 'string', 'max:2000'],
            'warna' => ['nullable', 'string', 'max:24'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], [
            'nama.unique' => 'Nama unit itu sudah dipakai pada jenis yang sama.',
        ]);

        $unit->jenis = $data['jenis'];
        $unit->nama = $data['nama'];
        $unit->singkatan = $data['singkatan'] ?? null;
        $unit->warna = $data['warna'] ?? null;
        $unit->urutan = $data['urutan'] ?? 0;
        $unit->aktif = (bool) ($data['aktif'] ?? true);

        $deskripsi = array_filter(
            $data['deskripsi'] ?? [],
            fn ($isi) => is_string($isi) && trim($isi) !== '',
        );

        $unit->setTranslations('deskripsi', $deskripsi);
    }

    private function slugUnik(string $nama, ?int $kecualiId): string
    {
        $dasar = Str::slug($nama) ?: 'unit';

        $slug = $dasar;
        $urutan = 2;

        while (OrganisationUnit::query()
            ->where('slug', $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists()
        ) {
            $slug = $dasar.'-'.$urutan;
            $urutan++;
        }

        return $slug;
    }
}
