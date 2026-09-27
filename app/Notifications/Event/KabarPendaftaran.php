<?php

namespace App\Notifications\Event;

use App\Models\EventRegistration;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu kelas untuk empat kabar pendaftaran event — mengikuti pola KabarArtikel
 * pada modul redaksi, supaya isi pesan tidak tersebar di banyak berkas.
 *
 * Dikirim lewat antrean dan dialamatkan langsung ke email pendaftar (bukan ke
 * akun), karena pendaftar event TIDAK punya akun di sistem.
 */
class KabarPendaftaran extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public const DITERIMA = 'diterima';

    public const DIVERIFIKASI = 'diverifikasi';

    public const DITOLAK = 'ditolak';

    public const DIBATALKAN = 'dibatalkan';

    public function __construct(
        public readonly EventRegistration $pendaftaran,
        public readonly string $keadaan = self::DITERIMA,
        public readonly ?string $catatan = null,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::EVENT;
    }

    public function judulPusat(): string
    {
        $nama = $this->pendaftaran->event?->getTranslation('judul', 'id', false) ?: 'Kegiatan';

        return match ($this->keadaan) {
            self::DIVERIFIKASI => 'Pendaftaran terverifikasi — '.$nama,
            self::DITOLAK => 'Pendaftaran belum dapat diterima — '.$nama,
            self::DIBATALKAN => 'Pendaftaran dibatalkan — '.$nama,
            default => 'Pendaftaran diterima — '.$nama,
        };
    }

    public function pesanPusat(): string
    {
        $pesan = 'Kode pendaftaranmu '.$this->pendaftaran->kode_pendaftaran.'.';

        return $this->catatan !== null && $this->catatan !== ''
            ? $pesan.' Catatan panitia: '.$this->catatan
            : $pesan;
    }

    public function tautanPusat(): ?string
    {
        return '/pendaftaran';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->pendaftaran->event;
        $namaEvent = $event?->getTranslation('judul', 'id', false) ?: 'Kegiatan';

        $pesan = (new MailMessage)
            ->greeting('Halo, '.$this->pendaftaran->nama_lengkap.'!')
            ->line('Pendaftaranmu untuk **'.$namaEvent.'** dengan kode **'.$this->pendaftaran->kode_pendaftaran.'**');

        switch ($this->keadaan) {
            case self::DIVERIFIKASI:
                $pesan->subject('Pendaftaran Terverifikasi — '.$namaEvent)
                    ->line('telah DIVERIFIKASI oleh panitia. Sampai jumpa di lokasi kegiatan!');
                break;

            case self::DITOLAK:
                $pesan->subject('Pendaftaran Belum Dapat Diterima — '.$namaEvent)
                    ->line('belum dapat kami terima.');
                if (filled($this->catatan)) {
                    $pesan->line('Catatan panitia: '.$this->catatan);
                }
                break;

            case self::DIBATALKAN:
                $pesan->subject('Pendaftaran Dibatalkan — '.$namaEvent)
                    ->line('telah dibatalkan. Kursimu kami kembalikan agar dapat dipakai peserta lain.');
                break;

            default:
                $pesan->subject('Pendaftaran Diterima — '.$namaEvent)
                    ->line('sudah kami terima dan sedang menunggu verifikasi panitia.')
                    ->line('Simpan kode di atas — dipakai untuk memeriksa status pendaftaranmu.');
                break;
        }

        if ($event?->mulai) {
            $pesan->line('Jadwal: '.$event->mulai->translatedFormat('d F Y')
                .($event->selesai && ! $event->selesai->equalTo($event->mulai) ? ' – '.$event->selesai->translatedFormat('d F Y') : ''));
        }

        if (filled($event?->lokasi)) {
            $pesan->line('Lokasi: '.$event->lokasi);
        }

        return $pesan->line('Terima kasih.');
    }
}
