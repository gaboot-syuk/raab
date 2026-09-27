<?php

namespace App\Notifications\Redaksi;

use App\Models\Article;
use App\Notifications\Concerns\MasukPusatNotifikasi;
use App\Services\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kabar redaksi: satu kelas untuk seluruh peristiwa alur artikel.
 *
 * Digabung agar isi pesan yang mirip tidak tersebar di banyak berkas, dan
 * supaya penambahan peristiwa baru cukup menambah satu cabang di sini.
 *
 * Jenis yang didukung:
 *  - ditugaskan  : artikel masuk antrean review → ke pengelola konten
 *  - terbit      : artikel tayang                → ke penulis
 *  - revisi      : naskah perlu diperbaiki       → ke penulis
 *  - ditolak     : naskah tidak diterbitkan      → ke penulis
 */
class KabarArtikel extends Notification implements ShouldQueue
{
    use MasukPusatNotifikasi;
    use Queueable;

    public const DITUGASKAN = 'ditugaskan';
    public const TERBIT = 'terbit';
    public const REVISI = 'revisi';
    public const DITOLAK = 'ditolak';

    public function __construct(
        public readonly string $jenis,
        public readonly Article $artikel,
        public readonly ?string $catatan = null,
    ) {}

    public function kategori(): string
    {
        return Notifikasi::REDAKSI;
    }

    public function judulPusat(): string
    {
        $judul = $this->judulArtikel();

        return match ($this->jenis) {
            self::DITUGASKAN => 'Artikel baru menunggu review: '.$judul,
            self::TERBIT => 'Artikelmu telah terbit: '.$judul,
            self::REVISI => 'Artikelmu perlu diperbaiki: '.$judul,
            self::DITOLAK => 'Artikelmu belum dapat diterbitkan: '.$judul,
            default => 'Kabar artikel: '.$judul,
        };
    }

    public function pesanPusat(): string
    {
        $pesan = 'Artikel "'.$this->judulArtikel().'".';

        return $this->catatan !== null && $this->catatan !== ''
            ? $pesan.' Catatan: '.$this->catatan
            : $pesan;
    }

    /**
     * Judul artikel dalam bahasa Indonesia.
     *
     * DITULIS LANGSUNG lewat `getTranslation()`, bukan lewat `judulTeks()`:
     * `App\Models\Article` memang TIDAK punya metode itu — berbeda dari model
     * lain. Memanggilnya membuat setiap pengiriman notifikasi redaksi gagal
     * dengan `BadMethodCallException`, dan itu baru ketahuan saat seluruh uji
     * dijalankan.
     */
    private function judulArtikel(): string
    {
        return $this->artikel->getTranslation('judul', 'id') ?: 'Artikel';
    }

    public function tautanPusat(): ?string
    {
        return '/panel/artikel';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $judul = (string) $this->artikel->getTranslation('judul', 'id', false);

        return match ($this->jenis) {
            self::DITUGASKAN => (new MailMessage)
                ->subject('Artikel baru menunggu review: '.$judul)
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Sebuah artikel baru telah dikirim untuk ditinjau.')
                ->line('**Judul:** '.$judul)
                ->line('**Penulis:** '.($this->artikel->penulis?->name ?? '—'))
                ->line('**Tipe:** '.$this->artikel->labelTipe())
                ->action('Buka Antrean Review', url('/panel/artikel?status=menunggu_review'))
                ->line('Terima kasih.'),

            self::TERBIT => (new MailMessage)
                ->subject('Artikelmu telah terbit: '.$judul)
                ->greeting('Selamat, '.$notifiable->name.'!')
                ->line('Artikelmu sudah tayang di situs PMII Rayon Ali Ahmad Baktsir.')
                ->line('**Judul:** '.$judul)
                ->action('Lihat Artikel', url('/publikasi/'.$this->artikel->tipe.'/'.$this->slug()))
                ->line('Terima kasih sudah menulis untuk rayon kita.'),

            self::REVISI => (new MailMessage)
                ->subject('Artikelmu perlu diperbaiki: '.$judul)
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Pengelola konten meninjau artikelmu dan meminta perbaikan.')
                ->line('**Catatan dari pengelola:**')
                ->line((string) $this->catatan)
                ->action('Perbaiki Artikel', url('/dasbor'))
                ->line('Artikelmu masih tersimpan — cukup perbarui bagian yang diminta.'),

            self::DITOLAK => (new MailMessage)
                ->subject('Artikelmu belum dapat diterbitkan: '.$judul)
                ->greeting('Halo '.$notifiable->name.',')
                ->line('Setelah ditelaah, artikelmu belum dapat kami terbitkan.')
                ->line('**Catatan dari pengelola:**')
                ->line((string) $this->catatan)
                ->line('Kamu dapat mengirimkan karya baru kapan saja.')
                ->action('Hubungi Pengelola', url('/kontak')),

            default => (new MailMessage)->subject('Kabar artikel'),
        };
    }

    private function slug(): string
    {
        return (string) $this->artikel->getTranslation('slug', 'id', false);
    }
}
