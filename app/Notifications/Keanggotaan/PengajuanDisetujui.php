<?php

namespace App\Notifications\Keanggotaan;

use App\Models\Member;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar baik: pengajuan keanggotaan disetujui.
 *
 * Dikirim lewat antrean agar pendaftaran tidak tertahan bila server surat
 * sedang lambat. Isi pesan menyesuaikan jalur: kader menerima nomor anggota &
 * tautan kartu, alumni diarahkan melengkapi profil alumni.
 */
class PengajuanDisetujui extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public function __construct(
        public readonly Member $member,
    ) {}

    /**
     * Kategori `keanggotaan` TIDAK BISA dimatikan pengguna: surat inilah yang
     * memberi tahu bahwa ia sudah boleh masuk.
     */
    public function kategori(): string
    {
        return Notifikasi::KEANGGOTAAN;
    }

    public function judulPusat(): string
    {
        return 'Pengajuan keanggotaanmu disetujui';
    }

    public function pesanPusat(): string
    {
        $nomor = $this->member->nomor_anggota;

        /*
         * NOMOR ANGGOTA BISA KOSONG. Persetujuan dan penerbitan nomor adalah dua
         * hal berbeda, dan ada keadaan di mana yang satu sudah terjadi sementara
         * yang lain belum. Ditemukan lewat peramban: pesannya terbaca
         * "Nomor anggotamu ." — kalimat yang rusak, dan membuat pengguna
         * mengira nomornya hilang.
         */
        return $nomor
            ? 'Nomor anggotamu '.$nomor.'. Kartu digitalnya sudah bisa dibuka dari dasbor.'
            : 'Pengajuanmu sudah disetujui. Nomor anggota akan diterbitkan pengurus, dan kartu digitalnya bisa dibuka dari dasbor.';
    }

    public function tautanPusat(): ?string
    {
        return '/dasbor';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kader = $this->member->jalur === Member::JALUR_KADER;

        $pesan = (new MailMessage)
            ->subject('Pengajuan Keanggotaan Disetujui — '.$this->member->nomor_anggota)
            ->greeting('Selamat, '.$this->member->nama_lengkap.'!')
            ->line($kader
                ? 'Pengajuanmu sebagai Kader Aktif PMII Rayon Ali Ahmad Baktsir telah disetujui.'
                : 'Pengajuanmu sebagai Alumni PMII Rayon Ali Ahmad Baktsir telah disetujui.')
            ->line('Nomor anggota kamu: **'.$this->member->nomor_anggota.'**');

        if ($kader) {
            $pesan->line('Kartu kader digitalmu sudah diterbitkan dan dapat dilihat pada dasbor.')
                ->line('Simpan nomor anggota ini — dipakai untuk presensi kegiatan dan peminjaman buku.');
        } else {
            $pesan->line('Silakan lengkapi profil alumunimu (instansi, domisili, kesediaan menjadi mentor) melalui dasbor.');
        }

        return $pesan
            ->action('Buka Dasbor', url('/dasbor'))
            ->line('Terima kasih telah menjadi bagian dari keluarga besar PMII RAAB.');
    }
}
