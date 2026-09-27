<?php

namespace App\Services;

use App\Models\AlumniProfile;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberCard;
use App\Models\MemberStatusHistory;
use App\Models\User;
use App\Notifications\Keanggotaan\PengajuanDisetujui;
use App\Notifications\Keanggotaan\PengajuanDitolak;
use App\Notifications\Keanggotaan\PengajuanPerluPerbaikan;
use App\Notifications\Keanggotaan\StatusKeanggotaanBerubah;
use App\Support\NomorAnggota;
use App\Support\NomorKartu;
use Illuminate\Support\Facades\DB;

/**
 * Aturan bisnis keanggotaan.
 *
 * Dikumpulkan di satu kelas supaya alur "setujui / tolak / ubah status" selalu
 * konsisten: nomor anggota dibuat, kartu diterbitkan atau dicabut, riwayat
 * tercatat, dan pemohon diberi kabar lewat email — tanpa ada langkah terlewat.
 *
 * Semua operasi yang mengubah data dibungkus transaksi.
 */
class Keanggotaan
{
    /**
     * Masa berlaku kartu kader (tahun).
     */
    public const MASA_KARTU_TAHUN = 2;

    /**
     * Setujui pengajuan → jadikan anggota resmi.
     *
     * Nomor anggota diterbitkan di sini (bukan saat mendaftar) supaya nomor
     * hanya dimiliki orang yang benar-benar sudah diverifikasi.
     */
    public function setujui(MemberApplication $pengajuan, User $pengurus): Member
    {
        return DB::transaction(function () use ($pengajuan, $pengurus): Member {
            $data = $pengajuan->data;
            $jalur = $pengajuan->jalur;

            $member = $pengajuan->member ?? new Member;
            $statusLama = $member->exists ? $member->status : null;
            $jalurLama = $member->exists ? $member->jalur : null;

            $member->fill([
                'user_id' => $pengajuan->user_id,
                'nama_lengkap' => $data['nama_lengkap'] ?? $pengajuan->user->name,
                'nama_panggilan' => $data['nama_panggilan'] ?? null,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'tempat_lahir' => $data['tempat_lahir'] ?? null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'nim' => $data['nim'] ?? null,
                'fakultas' => $data['fakultas'] ?? null,
                'program_studi' => $data['program_studi'] ?? null,
                'angkatan' => $data['angkatan'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'telepon' => $data['telepon'] ?? null,
                'email_kontak' => $data['email_kontak'] ?? null,
                'jalur' => $jalur,
                'status' => $jalur === Member::JALUR_ALUMNI ? Member::STATUS_ALUMNI : Member::STATUS_AKTIF,
                'diverifikasi_oleh' => $pengurus->id,
                'diverifikasi_pada' => now(),
                'catatan_verifikasi' => $pengajuan->catatan_pengurus,
            ]);

            if (! $member->exists) {
                $member->nomor_anggota ??= NomorAnggota::buat();
            }

            $member->nama_lengkap ??= $pengajuan->user->name;
            $member->save();

            $pengajuan->forceFill([
                'member_id' => $member->id,
                'status' => MemberApplication::STATUS_DISETUJUI,
                'diproses_oleh' => $pengurus->id,
                'diproses_pada' => now(),
            ])->save();

            $this->catatRiwayat($member, $statusLama, $member->status, $jalurLama, $jalur, $pengurus, 'Pengajuan disetujui.');

            // Kartu kader hanya untuk jalur kader; alumni tidak memerlukan kartu.
            if ($jalur === Member::JALUR_KADER) {
                $this->terbitkanKartu($member);
            } else {
                $this->siapkanProfilAlumni($member, $data);
            }

            $pengajuan->user->notify(new PengajuanDisetujui($member));

            return $member->refresh();
        });
    }

    public function tolak(MemberApplication $pengajuan, User $pengurus, string $catatan): void
    {
        DB::transaction(function () use ($pengajuan, $pengurus, $catatan): void {
            $pengajuan->forceFill([
                'status' => MemberApplication::STATUS_DITOLAK,
                'catatan_pengurus' => $catatan,
                'diproses_oleh' => $pengurus->id,
                'diproses_pada' => now(),
            ])->save();

            // Bila sebelumnya sempat menjadi anggota, tandai ditolak agar tidak
            // tampil di direktori.
            if ($pengajuan->member) {
                $this->ubahStatus($pengajuan->member, Member::STATUS_DITOLAK, $catatan, $pengurus, kirimEmail: false);
            }

            $pengajuan->user->notify(new PengajuanDitolak($pengajuan, $catatan));
        });
    }

    public function mintaPerbaikan(MemberApplication $pengajuan, User $pengurus, string $catatan): void
    {
        DB::transaction(function () use ($pengajuan, $pengurus, $catatan): void {
            $pengajuan->forceFill([
                'status' => MemberApplication::STATUS_PERBAIKAN,
                'catatan_pengurus' => $catatan,
                'diproses_oleh' => $pengurus->id,
                'diproses_pada' => now(),
            ])->save();

            $pengajuan->user->notify(new PengajuanPerluPerbaikan($pengajuan, $catatan));
        });
    }

    /**
     * Ubah status keanggotaan (mis. aktif → nonaktif, atau aktif → alumni).
     *
     * Kartu kader otomatis dicabut ketika anggota tidak lagi berstatus aktif.
     */
    public function ubahStatus(
        Member $member,
        string $statusBaru,
        ?string $alasan = null,
        ?User $pengurus = null,
        bool $kirimEmail = true,
    ): Member {
        return DB::transaction(function () use ($member, $statusBaru, $alasan, $pengurus, $kirimEmail): Member {
            $statusLama = $member->status;
            $jalurLama = $member->jalur;

            $jalurBaru = $statusBaru === Member::STATUS_ALUMNI ? Member::JALUR_ALUMNI : $member->jalur;

            $member->forceFill([
                'status' => $statusBaru,
                'jalur' => $jalurBaru,
            ])->save();

            $this->catatRiwayat($member, $statusLama, $statusBaru, $jalurLama, $jalurBaru, $pengurus, $alasan);

            if ($jalurBaru === Member::JALUR_ALUMNI) {
                $this->cabutKartu($member, $alasan ?? 'Status berubah menjadi alumni.');
                $this->siapkanProfilAlumni($member);
            } elseif ($statusBaru !== Member::STATUS_AKTIF) {
                $this->cabutKartu($member, $alasan ?? 'Status keanggotaan tidak aktif.');
            }

            if ($kirimEmail) {
                $member->user?->notify(new StatusKeanggotaanBerubah($member, $statusLama, $alasan));
            }

            return $member->refresh();
        });
    }

    /**
     * Naikkan status beberapa kader menjadi alumni sekaligus.
     *
     * @param  iterable<int, Member>  $daftar
     * @return int  jumlah anggota yang berhasil diubah
     */
    public function jadikanAlumniMassal(iterable $daftar, User $pengurus, ?string $alasan = null): int
    {
        $jumlah = 0;

        foreach ($daftar as $member) {
            if ($member->status === Member::STATUS_AKTIF) {
                $this->ubahStatus($member, Member::STATUS_ALUMNI, $alasan, $pengurus);
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /* ------------------------------------------------------------------ */
    /* Bagian dalam                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Terbitkan kartu kader bila belum ada; bila sudah ada, perbarui masa berlakunya.
     */
    public function terbitkanKartu(Member $member): MemberCard
    {
        $kartu = $member->kartu ?? new MemberCard(['member_id' => $member->id]);

        $kartu->fill([
            'member_id' => $member->id,
            // Nomor kartu memakai urutannya sendiri, TIDAK diturunkan dari
            // nomor anggota — lihat penjelasan lengkapnya di App\Support\NomorKartu.
            'nomor_kartu' => $kartu->nomor_kartu ?: NomorKartu::buat(),
            'token' => $kartu->token ?: MemberCard::buatToken(),
            'status' => MemberCard::STATUS_AKTIF,
            'berlaku_sampai' => now()->addYears(self::MASA_KARTU_TAHUN)->endOfYear(),
            'diterbitkan_pada' => $kartu->diterbitkan_pada ?? now(),
            'dicabut_pada' => null,
            'alasan_pencabutan' => null,
        ]);

        $kartu->save();

        // Relasi di memori DISAMAKAN dengan isi basis data. Tanpa ini,
        // `$member->kartu` masih bernilai null (hasil pembacaan sebelum kartu
        // dibuat) dan pemanggilan `cabutKartu($member)` berikutnya di permintaan
        // yang sama akan mengira kartunya tidak ada, lalu diam-diam tidak
        // mencabut apa pun.
        $member->setRelation('kartu', $kartu);

        return $kartu;
    }

    public function cabutKartu(Member $member, string $alasan): void
    {
        $kartu = $member->kartu;

        if (! $kartu || $kartu->status === MemberCard::STATUS_DICABUT) {
            return;
        }

        $kartu->forceFill([
            'status' => MemberCard::STATUS_DICABUT,
            'dicabut_pada' => now(),
            'alasan_pencabutan' => $alasan,
        ])->save();

        $member->setRelation('kartu', $kartu);
    }

    /**
     * Buat profil alumni kosong bila belum ada, agar direktori alumni lengkap.
     *
     * @param  array<string, mixed>  $data
     */
    private function siapkanProfilAlumni(Member $member, array $data = []): AlumniProfile
    {
        return AlumniProfile::query()->firstOrCreate(
            ['member_id' => $member->id],
            [
                'tahun_lulus' => $data['tahun_lulus'] ?? null,
                'instansi' => $data['instansi'] ?? null,
                'jabatan' => $data['jabatan'] ?? null,
                'bidang' => $data['bidang'] ?? null,
                'kota_domisili' => $data['kota_domisili'] ?? null,
                'kontak_publik' => [],
                'bersedia_mentor' => false,
            ],
        );
    }

    private function catatRiwayat(
        Member $member,
        ?string $statusLama,
        string $statusBaru,
        ?string $jalurLama,
        ?string $jalurBaru,
        ?User $pengurus,
        ?string $alasan,
    ): void {
        MemberStatusHistory::query()->create([
            'member_id' => $member->id,
            'status_lama' => $statusLama,
            'status_baru' => $statusBaru,
            'jalur_lama' => $jalurLama,
            'jalur_baru' => $jalurBaru,
            'alasan' => $alasan,
            'diubah_oleh' => $pengurus?->id,
        ]);
    }
}
