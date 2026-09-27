<?php

namespace App\Notifications\Keuangan;

use App\Models\DueInvoice;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat tagihan iuran untuk anggota.
 *
 * NADA SURAT INI SENGAJA MEMINTA, BUKAN MENAGIH. Rayon ini himpunan kader,
 * bukan lembaga kredit: yang perlu disampaikan hanya jumlah, tenggat, dan cara
 * membayar. Tidak ada kata "tunggakan", tidak ada ancaman, dan tidak ada
 * nominal denda — sebab denda memang tidak pernah diterapkan di sini.
 *
 * Notifikasi memegang DueInvoice yang SUDAH tersimpan. Notifikasi berantrean
 * diserialisasi oleh Laravel dan modelnya dimuat ulang lewat id, jadi model
 * tanpa id akan meledak dengan ModelNotFoundException tepat saat dikirim.
 */
class PengingatIuran extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public function __construct(
        public readonly DueInvoice $tagihan,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::KEUANGAN;
    }

    public function judulPusat(): string
    {
        return 'Pengingat iuran '.$this->tagihan->periode_label;
    }

    public function pesanPusat(): string
    {
        return 'Iuran periode '.$this->tagihan->periode_label.' sebesar Rp'
            .number_format($this->tagihan->nominal, 0, ',', '.')
            .' belum tercatat lunas. Ini sekadar pengingat — tidak ada denda.';
    }

    public function tautanPusat(): ?string
    {
        return '/iuran';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tagihan = $this->tagihan;
        $anggota = $tagihan->anggota;
        $kategori = $tagihan->kategori;

        $nominal = 'Rp'.number_format($tagihan->nominal, 0, ',', '.');

        $pesan = (new MailMessage)
            ->subject('Pengingat Iuran '.($kategori?->namaTeks() ?? 'Anggota').' — '.$tagihan->periode_label)
            ->greeting('Halo, '.($anggota?->nama_lengkap ?? 'Kader').'!')
            ->line('Kami mencatat iuran berikut belum tercatat lunas:')
            ->line('**'.($kategori?->namaTeks() ?? 'Iuran').'** · periode '.$tagihan->periode_label)
            ->line('Jumlah: **'.$nominal.'**');

        if ($tagihan->jatuh_tempo) {
            $pesan->line('Mohon diselesaikan sebelum **'.$tagihan->jatuh_tempo->translatedFormat('d F Y').'**.');
        }

        if ($tagihan->status === DueInvoice::STATUS_DITOLAK) {
            $pesan->line('Bukti transfer yang sebelumnya diunggah belum dapat kami terima. Silakan unggah ulang melalui halaman Iuran di area anggota.');
        } else {
            $pesan->line('Pembayaran dapat dilakukan tunai ke Bendahara, atau transfer dengan bukti yang diunggah melalui halaman Iuran di area anggota.');
        }

        return $pesan
            ->action('Buka Halaman Iuran', url('/iuran'))
            ->line('Bila kamu sudah membayar dan buktinya sedang diperiksa, abaikan saja surat ini.')
            ->line('Terima kasih.');
    }
}
