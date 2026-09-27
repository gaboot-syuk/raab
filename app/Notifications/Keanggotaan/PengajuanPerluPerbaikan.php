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
 * Pengajuan perlu diperbaiki sebelum dapat disetujui.
 *
 * Berbeda dari penolakan: pendaftar masih punya kesempatan, cukup melengkapi
 * atau membetulkan data yang disebutkan pengurus.
 */
class PengajuanPerluPerbaikan extends Notification implements ShouldQueue
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
        return 'Berkas pengajuanmu perlu diperbaiki';
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
            ->subject('Pengajuan Keanggotaan Perlu Diperbaiki')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuanmu sudah kami terima, namun masih ada beberapa hal yang perlu dilengkapi.')
            ->line('**Catatan dari pengurus:**')
            ->line($this->catatan)
            ->line('Silakan perbarui data melalui dasbor, lalu kirim ulang untuk diverifikasi. Pengajuanmu tetap kami simpan.')
            ->action('Perbarui Pengajuan', url('/dasbor'))
            ->line('Terima kasih atas kesabarannya.');
    }
}
