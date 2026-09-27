<?php

namespace App\Notifications\Keanggotaan;

use App\Models\MemberApplication;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengajuan keanggotaan ditolak, disertai alasan dari pengurus.
 *
 * Alasan wajib diisi pengurus (dijaga di sisi validasi controller) supaya
 * pemohon memahami penyebabnya dan dapat memperbaiki di kemudian hari.
 */
class PengajuanDitolak extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public function __construct(
        public readonly MemberApplication $pengajuan,
        public readonly string $catatan,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::KEANGGOTAAN;
    }

    public function judulPusat(): string
    {
        return 'Pengajuan keanggotaanmu belum dapat diterima';
    }

    public function pesanPusat(): string
    {
        return 'Catatan pengurus: '.$this->catatan;
    }

    public function tautanPusat(): ?string
    {
        return '/dasbor';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan Keanggotaan Belum Dapat Disetujui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Terima kasih telah mendaftar di PMII Rayon Ali Ahmad Baktsir. Setelah ditelaah, pengajuanmu belum dapat kami setujui.')
            ->line('**Catatan dari pengurus:**')
            ->line($this->catatan)
            ->line('Kamu dapat mendaftar ulang bila keadaan sudah memungkinkan, atau menghubungi sekretariat untuk bertanya lebih lanjut.')
            ->action('Hubungi Sekretariat', url('/kontak'))
            ->line('Semoga kita tetap dapat bertemu di lain kesempatan.');
    }
}
