<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AlumniProfile;
use App\Models\Member;
use App\Models\OrganisationUnit;
use App\Support\Pengaturan;
use App\Support\StatistikAman;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Direktori publik anggota & alumni.
 *
 * Halaman ini TIDAK menampilkan identitas siapa pun. Nama, NIM, telepon,
 * email, alamat, keahlian, dan tautan media sosial tidak diambil dari basis
 * data untuk keperluan ini (lihat Member::untukDirektori) — jadi bukan
 * disaring di tampilan, melainkan memang tidak pernah ada di halaman. Nama
 * yang sudah telanjur diindeks mesin pencari tidak dapat ditarik kembali.
 *
 * Yang tampil: atribut akademis dan organisatoris, rincian statistik, dan
 * saringan. Rincian statistik disamarkan bila kelompoknya terlalu kecil —
 * lihat App\Support\StatistikAman. Tanpa itu, menyaring direktori sampai
 * tersisa satu orang akan mengembalikan identitas yang baru saja dihapus,
 * hanya kali ini lewat angka.
 */
class DirektoriController extends Controller
{
    /**
     * Kolom yang boleh dirinci di halaman publik.
     *
     * Daftar putih, bukan sekadar niat baik: nama kolom ini disisipkan ke
     * dalam SQL, sehingga nilai yang datang dari luar harus ditolak.
     */
    private const KOLOM_ANGGOTA = ['angkatan', 'program_studi'];

    private const KOLOM_ALUMNI = ['tahun_lulus', 'bidang', 'kota_domisili', 'instansi'];

    /** Kolom yang tunduk pada izin pemilik data (alumni_profiles.kontak_publik). */
    private const KOLOM_ALUMNI_BERIZIN = ['kota_domisili', 'instansi'];

    public function anggota(Request $request): View
    {
        $saring = [
            'angkatan' => $request->string('angkatan')->toString(),
            'unit' => $request->string('unit')->toString(),
            'fakultas' => $request->string('fakultas')->toString(),
            'prodi' => $request->string('prodi')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $kueri = $this->kueriAnggota($saring);

        /*
         * Urutan memakai atribut publik, BUKAN nama.
         *
         * Mengurutkan menurut nama membuat kartu-kartu anonim tersusun
         * mengikuti abjad nama. Cukup dengan mengetahui daftar nama kadernya
         * dari tempat lain, urutan itu sudah menunjukkan kartu mana milik
         * siapa — identitasnya kembali tanpa namanya pernah dicetak.
         */
        $daftar = (clone $kueri)
            ->with(['unit:id,nama'])
            ->orderByDesc('angkatan')
            ->orderBy('program_studi')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Member $anggota): array => $anggota->untukDirektori());

        return view('public.anggota', [
            'situs' => Pengaturan::semua(),
            'daftar' => $daftar,
            'saring' => $saring,
            'daftarAngkatan' => $this->daftarAngkatan(Member::STATUS_AKTIF),
            'daftarUnit' => $this->daftarUnit(),
            'daftarFakultas' => $this->daftarKolom('fakultas', Member::STATUS_AKTIF),
            'daftarProdi' => $this->daftarKolom('program_studi', Member::STATUS_AKTIF),
            'statistik' => [
                'total' => $this->total($kueri),
                'angkatan' => $this->rincianKolom($kueri, 'angkatan', self::KOLOM_ANGGOTA, 'desc'),
                'prodi' => $this->rincianKolom($kueri, 'program_studi', self::KOLOM_ANGGOTA),
                'unit' => $this->rincianUnit($kueri),
            ],
        ]);
    }

    public function alumni(Request $request): View
    {
        $saring = [
            'tahun_lulus' => $request->string('tahun_lulus')->toString(),
            'bidang' => $request->string('bidang')->toString(),
            'domisili' => trim($request->string('domisili')->toString()),
            'instansi' => trim($request->string('instansi')->toString()),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $kueri = $this->kueriAlumni($saring);

        $daftar = (clone $kueri)
            ->with(['unit:id,nama', 'profilAlumni'])
            ->orderByDesc('angkatan')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString()
            ->through(function (Member $anggota): array {
                $profil = $anggota->profilAlumni;

                /*
                 * Untuk alumni, yang menentukan adalah pilihan pemiliknya di
                 * `alumni_profiles.kontak_publik` — bukan `members.privasi`.
                 * Berkas ini pernah mencampur keduanya sehingga satu sakelar
                 * tidak berpengaruh apa pun.
                 */
                $boleh = fn (string $bagian): bool => $profil?->bolehTampil($bagian)
                    ?? $anggota->bolehTampil($bagian);

                /*
                 * Hanya empat kolom ini yang tetap tampil: tahun lulus,
                 * bidang, domisili, dan instansi/jabatan.
                 *
                 * Kontak (telepon, email) dan kesediaan menjadi mentor TIDAK
                 * lagi ikut. Nilainya pernah bergantung pada izin pemilik
                 * data, tetapi tanpa nama di kartunya, kontak tidak lagi
                 * berguna untuk siapa pun — sementara bagi pemiliknya sendiri
                 * ia tetap berarti membuka satu pintu lebih lebar daripada
                 * yang diminta.
                 */
                return [
                    ...$anggota->untukDirektori(),
                    'tahun_lulus' => $profil?->tahun_lulus,
                    'bidang' => $profil?->bidang,
                    'domisili' => $boleh('kota_domisili') ? $profil?->kota_domisili : null,
                    'instansi' => $boleh('instansi') ? $profil?->instansi : null,
                    'jabatan' => $boleh('instansi') ? $profil?->jabatan : null,
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
            'statistik' => [
                'total' => $this->total($kueri),
                'tahun_lulus' => $this->rincianProfil($kueri, 'tahun_lulus'),
                'bidang' => $this->rincianProfil($kueri, 'bidang'),
                'domisili' => $this->rincianProfil($kueri, 'kota_domisili'),
                'instansi' => $this->rincianProfil($kueri, 'instansi'),
            ],

            /*
             * Titik peta sebaran.
             *
             * Isi popup dikosongkan dari identitas: tanpa nama, tanpa instansi,
             * dan tanpa tautan ke profil kader. Tautan itu terutama berbahaya —
             * alamatnya memuat slug yang berasal dari nama orang, sehingga
             * satu klik sudah cukup membatalkan seluruh penyamaran di halaman
             * ini. Yang tersisa hanya kota dan tahun lulus.
             *
             * Titiknya sendiri tetap berasal dari koordinat yang diisi alumni
             * bersangkutan; yang tidak mengisi tidak muncul di sini.
             */
            'peta' => AlumniProfile::query()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereHas('member', fn ($q) => $q->where('status', Member::STATUS_ALUMNI))
                ->get()
                ->map(fn (AlumniProfile $profil): array => [
                    'lat' => (float) $profil->latitude,
                    'lng' => (float) $profil->longitude,
                    'kota' => $profil->kota_domisili,
                    'tahun_lulus' => $profil->tahun_lulus,
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

    /**
     * Kueri dasar kader aktif dengan seluruh saringan — TANPA urutan dan
     * paginasi.
     *
     * Dipisahkan supaya kueri yang sama dapat dipakai ulang untuk menghitung
     * statistik. Kalau statistik dihitung dari kueri yang berbeda, cepat atau
     * lambat angkanya akan berbeda dari daftarnya — dan tidak ada yang tahu
     * mana yang benar.
     *
     * @param  array<string, string>  $saring
     */
    private function kueriAnggota(array $saring): Builder
    {
        return Member::query()
            ->aktif()
            ->when($saring['angkatan'] !== '', fn ($q) => $q->where('angkatan', (int) $saring['angkatan']))
            ->when($saring['unit'] !== '', fn ($q) => $q->where('unit_id', (int) $saring['unit']))
            ->when($saring['fakultas'] !== '', fn ($q) => $q->where('fakultas', $saring['fakultas']))
            ->when($saring['prodi'] !== '', fn ($q) => $q->where('program_studi', $saring['prodi']))
            /*
             * Pencarian bebas hanya menyentuh program studi dan fakultas.
             * Sebelumnya ia juga mencocokkan nama dan NIM — artinya siapa pun
             * bisa membuktikan "apakah si X kader rayon ini" dengan mengetik
             * namanya, dan bahkan memastikan NIM-nya. Tanpa nama di layar,
             * kotak itu berubah dari alat bantu menjadi alat pembuktian.
             */
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('program_studi', 'like', "%{$saring['cari']}%")
                    ->orWhere('fakultas', 'like', "%{$saring['cari']}%"),
            ));
    }

    /**
     * Kueri dasar alumni dengan seluruh saringan — TANPA urutan dan paginasi.
     *
     * Saringan yang menyentuh tabel profil alumni memakai whereHas agar alumni
     * tanpa profil tidak menghilang dari daftar saat tidak ada saringan.
     *
     * @param  array<string, string>  $saring
     */
    private function kueriAlumni(array $saring): Builder
    {
        return Member::query()
            ->alumni()
            ->when($saring['tahun_lulus'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'tahun_lulus', (int) $saring['tahun_lulus']))
            ->when($saring['bidang'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'bidang', $saring['bidang']))
            ->when($saring['domisili'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'kota_domisili', 'like', "%{$saring['domisili']}%"))
            ->when($saring['instansi'] !== '', fn ($q) => $q->whereRelation('profilAlumni', 'instansi', 'like', "%{$saring['instansi']}%"))
            ->when($saring['cari'] !== '', fn ($q) => $q->whereHas('profilAlumni', fn ($qq) => $qq
                ->where('instansi', 'like', "%{$saring['cari']}%")
                ->orWhere('bidang', 'like', "%{$saring['cari']}%")
                ->orWhere('kota_domisili', 'like', "%{$saring['cari']}%")));
    }

    /**
     * Daftar unit aktif untuk mengisi pilihan saringan.
     */
    private function daftarUnit()
    {
        return OrganisationUnit::query()
            ->aktif()
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->get(['id', 'nama', 'jenis']);
    }

    /**
     * Jumlah total hasil saring, sudah disamarkan bila kelompoknya kecil.
     *
     * @return array{jumlah: int, tampil: string}
     */
    private function total(Builder $kueri): array
    {
        $jumlah = (clone $kueri)->count();

        return [
            'jumlah' => $jumlah,
            'tampil' => StatistikAman::samar($jumlah),
        ];
    }

    /**
     * Rincian jumlah per nilai sebuah kolom pada tabel anggota.
     *
     * @param  array<int, string>  $diizinkan  Daftar putih nama kolom.
     * @return Collection<int, array{label: string, jumlah: int, tampil: string}>
     */
    private function rincianKolom(Builder $kueri, string $kolom, array $diizinkan, string $arah = 'asc'): Collection
    {
        if (! in_array($kolom, $diizinkan, true)) {
            throw new InvalidArgumentException("Kolom rincian tidak diizinkan: {$kolom}");
        }

        return (clone $kueri)
            ->selectRaw("{$kolom} as label, count(*) as jumlah")
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->groupBy($kolom)
            ->orderBy($kolom, $arah)
            ->get()
            ->map(fn (Member $baris): array => $this->barisRincian((string) $baris->label, (int) $baris->jumlah))
            ->values();
    }

    /**
     * Rincian jumlah per nilai sebuah kolom pada tabel profil alumni.
     *
     * DUA JALAN, dan pemilihannya bukan soal selera:
     *
     * - `tahun_lulus` dan `bidang` selalu publik, jadi cukup dihitung dengan
     *   GROUP BY di basis data.
     * - `kota_domisili` dan `instansi` tunduk pada izin pemilik data, dan
     *   izinnya dibaca lewat `AlumniProfile::bolehTampil()` — fungsi yang SAMA
     *   dengan yang dipakai kartu alumni di halaman ini.
     *
     * Menuliskannya ulang sebagai syarat SQL memang lebih cepat, tetapi
     * menghasilkan dua sumber kebenaran. Cukup satu perbedaan kecil di
     * antaranya, dan statistik publik akan menyebut instansi yang sengaja
     * disembunyikan pemiliknya — tepat cacat yang pernah terjadi di sini.
     *
     * @return Collection<int, array{label: string, jumlah: int, tampil: string}>
     */
    private function rincianProfil(Builder $kueri, string $kolom): Collection
    {
        if (! in_array($kolom, self::KOLOM_ALUMNI, true)) {
            throw new InvalidArgumentException("Kolom profil alumni tidak diizinkan: {$kolom}");
        }

        if (in_array($kolom, self::KOLOM_ALUMNI_BERIZIN, true)) {
            return $this->rincianBerizin($kueri, $kolom);
        }

        $nama = 'alumni_profiles.'.$kolom;

        return (clone $kueri)
            ->join('alumni_profiles', 'alumni_profiles.member_id', '=', 'members.id')
            ->selectRaw("{$nama} as label, count(*) as jumlah")
            ->whereNotNull($nama)
            ->where($nama, '!=', '')
            ->groupBy($nama)
            ->orderByDesc('jumlah')
            ->orderBy('label')
            ->get()
            ->map(fn (Member $baris): array => $this->barisRincian((string) $baris->label, (int) $baris->jumlah))
            ->values();
    }

    /**
     * Rincian untuk kolom yang tunduk pada izin pemilik data.
     *
     * Dihitung di PHP supaya syaratnya benar-benar sama dengan kartu alumni.
     * Profil yang belum ada ikut tersaring — kolom-kolom ini memang berada di
     * tabel profil, jadi tanpa profil tidak ada yang bisa dirinci.
     *
     * Jumlah alumni sebuah rayon masih ratusan, jadi memuat seluruhnya di sini
     * wajar. Bila kelak mencapai ribuan, gantilah dengan syarat SQL — tetapi
     * hanya setelah ada uji yang membuktikan hasilnya setara.
     *
     * @return Collection<int, array{label: string, jumlah: int, tampil: string}>
     */
    private function rincianBerizin(Builder $kueri, string $kolom): Collection
    {
        $profil = (clone $kueri)
            ->with(['profilAlumni' => fn ($q) => $q->select(['id', 'member_id', 'kontak_publik', $kolom])])
            ->get()
            ->pluck('profilAlumni')
            ->filter(fn (?AlumniProfile $profil): bool => $profil !== null
                && $profil->bolehTampil($kolom)
                && filled($profil->{$kolom}));

        return $profil
            ->groupBy(fn (AlumniProfile $profil): string => (string) $profil->{$kolom})
            ->map(fn (Collection $kelompok, string $nilai): array => $this->barisRincian($nilai, $kelompok->count()))
            ->sortBy([['jumlah', 'desc'], ['label', 'asc']])
            ->values();
    }

    /**
     * Rincian jumlah per unit organisasi (biro & LSO).
     *
     * @return Collection<int, array{label: string, jumlah: int, tampil: string}>
     */
    private function rincianUnit(Builder $kueri): Collection
    {
        $hitung = (clone $kueri)
            ->selectRaw('unit_id, count(*) as jumlah')
            ->whereNotNull('unit_id')
            ->groupBy('unit_id')
            ->get()
            ->mapWithKeys(fn (Member $baris): array => [(int) $baris->unit_id => (int) $baris->jumlah]);

        if ($hitung->isEmpty()) {
            return collect();
        }

        return OrganisationUnit::query()
            ->whereIn('id', $hitung->keys())
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->get(['id', 'nama'])
            ->map(fn (OrganisationUnit $unit): array => $this->barisRincian($unit->nama, $hitung[$unit->id] ?? 0))
            ->values();
    }

    /**
     * Satu baris rincian statistik, lengkap dengan angka yang sudah disamarkan.
     *
     * Angka mentahnya tetap ikut supaya dapat diuji dan dipakai ulang;
     * `tampil` yang dirender ke halaman.
     *
     * @return array{label: string, jumlah: int, tampil: string}
     */
    private function barisRincian(string $label, int $jumlah): array
    {
        return [
            'label' => $label,
            'jumlah' => $jumlah,
            'tampil' => StatistikAman::samar($jumlah),
        ];
    }
}
