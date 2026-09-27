<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Models\Period;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\UnitAgenda;
use App\Support\PustakaMedia;
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

    /**
     * Halaman kelola SATU unit: pengurus, anggota, dan agenda.
     *
     * Sebelumnya ketiganya hanya dapat diatur dari halaman terpisah —
     * Penugasan, Keanggotaan, dan Galeri — lalu disaring sendiri satu per
     * satu. Untuk merapikan satu LSO saja, pengurus harus berpindah tiga
     * halaman sambil mengingat nama unitnya. Halaman ini mengumpulkannya.
     *
     * Agenda hanya disajikan untuk LSO: biro adalah bagian struktural rayon
     * dan tidak menyelenggarakan agenda sendiri.
     */
    public function detail(OrganisationUnit $unit): Response
    {
        $unit->loadCount(['jabatan', 'anggota', 'galeri']);

        // Periode berjalan dipakai sebagai konteks kepengurusan. Bila belum ada
        // yang ditandai berjalan, periode terbaru dipakai supaya jabatan yang
        // sudah terisi tidak tampak kosong tanpa penjelasan.
        $periode = Period::sedangAktif()
            ?? Period::query()->orderByDesc('tahun_selesai')->first();

        $jabatan = $unit->jabatan()
            ->orderBy('level')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $pengurus = ($jabatan->isEmpty() || ! $periode)
            ? collect()
            : PositionAssignment::query()
                ->where('period_id', $periode->id)
                ->whereIn('position_id', $jabatan->pluck('id'))
                ->with(['member:id,nama_lengkap,nomor_anggota', 'jabatan:id,nama,level'])
                ->orderBy('urutan')
                ->orderBy('id')
                ->get()
                ->map(fn (PositionAssignment $item): array => [
                    'id' => $item->id,
                    'position_id' => $item->position_id,
                    'jabatan' => $item->jabatan?->nama,
                    'nama' => $item->namaTampil(),
                    'nomor_anggota' => $item->member?->nomor_anggota,
                    'manual' => blank($item->member_id),
                    'keterangan' => $item->keterangan,
                    'aktif' => $item->aktif,
                ]);

        $agendaMentah = $unit->agenda()
            ->orderByDesc('mulai')
            ->get();

        // Satu kueri untuk SEMUA gambar agenda, bukan satu kueri per agenda.
        $gambarAgenda = PustakaMedia::peta($agendaMentah->pluck('gambar_media_id'));

        return Inertia::render('Panel/Organisasi/UnitDetail', [
            'unit' => [
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
                'lso' => $unit->jenis === OrganisationUnit::JENIS_LSO,
            ],
            'periode' => $periode
                ? ['id' => $periode->id, 'nama' => $periode->nama, 'aktif' => $periode->aktif]
                : null,
            'jabatan' => $jabatan->map(fn (Position $item): array => [
                'id' => $item->id,
                'nama' => $item->nama,
                'level' => $item->level,
                'aktif' => $item->aktif,
            ]),
            'pengurus' => $pengurus,
            'anggota' => $unit->anggota()
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nomor_anggota', 'status', 'slug'])
                ->map(fn (Member $orang): array => [
                    'id' => $orang->id,
                    'nama' => $orang->nama_lengkap,
                    'nomor_anggota' => $orang->nomor_anggota,
                    'status' => $orang->status,
                    'label_status' => Member::STATUS[$orang->status] ?? $orang->status,
                ]),
            'agenda' => $agendaMentah->map(fn (UnitAgenda $item): array => [
                'id' => $item->id,
                'judul' => $item->getTranslations('judul'),
                'mulai' => $item->mulai?->format('Y-m-d\TH:i'),
                'selesai' => $item->selesai?->format('Y-m-d\TH:i'),
                'lokasi' => $item->lokasi,
                'publik' => $item->publik,
                'gambar_media_id' => $item->gambar_media_id,
                'gambar' => $item->gambar_media_id ? ($gambarAgenda[$item->gambar_media_id] ?? null) : null,
            ]),
            'pilihanGambar' => PustakaMedia::pilihan(),
            'pilihanAnggota' => Member::query()
                ->whereIn('status', [Member::STATUS_AKTIF, Member::STATUS_ALUMNI])
                ->orderBy('nama_lengkap')
                ->limit(500)
                ->get(['id', 'nama_lengkap', 'nomor_anggota', 'unit_id'])
                ->map(fn (Member $orang): array => [
                    'id' => $orang->id,
                    'label' => $orang->nama_lengkap.($orang->nomor_anggota ? ' — '.$orang->nomor_anggota : ''),
                    'unit_id' => $orang->unit_id,
                ]),
        ]);
    }

    /**
     * Menautkan anggota ke unit ini.
     *
     * Satu anggota hanya berada di SATU unit — memindahkannya menimpa
     * `unit_id` yang lama, bukan menambah keanggotaan kedua. Karena itu
     * pesannya membedakan "dipindahkan" dari "ditambahkan".
     */
    public function tambahAnggota(Request $request, OrganisationUnit $unit): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $anggota = Member::query()->findOrFail($data['member_id']);
        $asal = $anggota->unit_id;

        $anggota->unit_id = $unit->id;
        $anggota->save();

        return back()->with('sukses', ($asal && (int) $asal !== (int) $unit->id)
            ? 'Anggota '.$anggota->nama_lengkap.' dipindahkan ke '.$unit->nama.'.'
            : 'Anggota '.$anggota->nama_lengkap.' ditambahkan ke '.$unit->nama.'.');
    }

    /**
     * Melepas anggota dari unit.
     *
     * Hanya melepas kaitannya; anggota TIDAK dihapus dan riwayat kepengurusan
     * beserta jabatannya tetap utuh.
     */
    public function lepasAnggota(OrganisationUnit $unit, Member $anggota): RedirectResponse
    {
        if ((int) $anggota->unit_id !== (int) $unit->id) {
            return back()->with('galat', 'Anggota itu tidak terdaftar di unit ini.');
        }

        $anggota->unit_id = null;
        $anggota->save();

        return back()->with('sukses', 'Anggota '.$anggota->nama_lengkap.' dilepas dari '.$unit->nama.'.');
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
