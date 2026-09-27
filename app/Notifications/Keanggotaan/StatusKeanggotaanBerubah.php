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
 * Pemberitahuan bahwa status keanggotaan berubah.
 *
 * Dipakai antara lain saat kader naik menjadi alumni. Isi pesan menjelaskan
 * konsekuensinya: kartu kader dicabut dan akses fitur kader berakhir.
 */
class StatusKeanggotaanBerubah extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public function __construct(
        public readonly Member $member,
        public readonly string $statusLama,
        public readonly ?string $alasan,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::KEANGGOTAAN;
    }

    public function judulPusat(): string
    {
        return 'Status keanggotaanmu berubah';
    }

    public function pesanPusat(): string
    {
        $pesan = 'Dari '.$this->statusLama.' menjadi '.$this->member->status.'.';

        return $this->alasan !== null && $this->alasan !== '' ? $pesan.' Alasan: '.$this->alasan : $pesan;
    }

    public function tautanPusat(): ?string
    {
        return '/dasbor';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject('Status Keanggotaan Diperbarui — '.$this->member->labelStatus())
            ->greeting('Halo '.$this->member->nama_lengkap.',')
            ->line('Status keanggotaanmu di PMII Rayon Ali Ahmad Baktsir telah diperbarui.')
            ->line('**Status baru:** '.$this->member->labelStatus());

        if ($this->alasan) {
            $pesan->line('**Keterangan:** '.$this->alasan);
        }

        if ($this->member->status === Member::STATUS_ALUMNI) {
            $pesan->line('Kartu kadermu tidak lagi berlaku untuk presensi dan peminjaman.')
                ->line('Namun sebagai alumni, kamu tetap dapat mengakses direktori alumni, mengajukan hibah, dan mendaftar sebagai mentor.')
                ->action('Lengkapi Profil Alumni', url('/dasbor'));
        }

        return $pesan->line('Terima kasih atas dedikasimu selama ini.');
    }
}
