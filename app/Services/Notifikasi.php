<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Pusat notifikasi.
 *
 * DUA KANAL, DUA ATURAN YANG BERBEDA — dan bedanya itulah inti modul ini:
 *
 *  - DALAM APLIKASI selalu aktif, untuk semua kategori, tanpa kecuali.
 *    Mematikannya hanya akan membuat orang kehilangan kabar tanpa sadar.
 *  - EMAIL bisa diatur per kategori, kecuali kategori yang ditandai WAJIB.
 *
 * KATEGORI `keanggotaan` TIDAK BISA DIMATIKAN. Surat dari kategori itulah yang
 * memberi tahu seseorang bahwa pengajuan keanggotaannya diterima — artinya,
 * bahwa ia sudah boleh masuk. Kalau surat itu bisa dimatikan, orang yang
 * mematikannya sebelum tahu apa pun tidak akan pernah tahu bahwa ia diterima,
 * dan tidak ada halaman lain yang bisa memberitahunya karena ia belum bisa
 * masuk. Karena itu sakelarnya memang tidak ada, bukan sekadar disembunyikan.
 */
final class Notifikasi
{
    public const KEANGGOTAAN = 'keanggotaan';

    public const PUSTAKA = 'pustaka';

    public const KEUANGAN = 'keuangan';

    public const REDAKSI = 'redaksi';

    public const EVENT = 'event';

    public const KEGIATAN = 'kegiatan';

    public const PRESTASI = 'prestasi';

    public const ASPIRASI = 'aspirasi';

    /**
     * Kategori yang emailnya tidak boleh dimatikan.
     *
     * @var array<int, string>
     */
    public const WAJIB = [self::KEANGGOTAAN];

    /**
     * @var array<string, string>
     */
    public const KATEGORI = [
        self::KEANGGOTAAN => 'Keanggotaan',
        self::PUSTAKA => 'Perpustakaan & Peminjaman',
        self::KEUANGAN => 'Keuangan & Iuran',
        self::REDAKSI => 'Redaksi & Publikasi',
        self::EVENT => 'Pendaftaran Event',
        self::KEGIATAN => 'Kegiatan & Presensi',
        self::PRESTASI => 'Prestasi',
        self::ASPIRASI => 'Aspirasi',
    ];

    /**
     * @var array<string, string>
     */
    public const KETERANGAN = [
        self::KEANGGOTAAN => 'Kabar tentang pengajuan dan status keanggotaanmu. Selalu dikirim lewat email — surat inilah yang memberi tahu bahwa kamu sudah bisa masuk.',
        self::PUSTAKA => 'Buku siap diambil, pengingat jatuh tempo, dan perpanjangan peminjaman.',
        self::KEUANGAN => 'Pengingat iuran dan pemberitahuan pembayaran.',
        self::REDAKSI => 'Naskahmu terbit, perlu direvisi, atau ditolak.',
        self::EVENT => 'Kabar tentang pendaftaran Mapaba/PKD yang kamu ikuti.',
        self::KEGIATAN => 'Kegiatan baru dan hasil presensi.',
        self::PRESTASI => 'Prestasimu diverifikasi atau ditolak.',
        self::ASPIRASI => 'Tanggapan pengurus atas aspirasimu.',
    ];

    /**
     * @var array<string, string>
     */
    private const IKON = [
        self::KEANGGOTAAN => '👥',
        self::PUSTAKA => '▥',
        self::KEUANGAN => 'Rp',
        self::REDAKSI => '✎',
        self::EVENT => '◇',
        self::KEGIATAN => '◷',
        self::PRESTASI => '◈',
        self::ASPIRASI => '✉',
    ];

    public static function ikon(string $kategori): string
    {
        return self::IKON[$kategori] ?? '•';
    }

    public static function label(string $kategori): string
    {
        return self::KATEGORI[$kategori] ?? $kategori;
    }

    /* ------------------------------------------------------------------ */
    /* Kanal                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Kanal yang dipakai sebuah notifikasi.
     *
     * Dipanggil dari `via()` tiap kelas notifikasi. Hanya ada satu tempat yang
     * memutuskan "email atau tidak", supaya aturannya tidak bercabang dan
     * tidak ada kelas yang diam-diam lupa menghormati preferensi.
     *
     * @return array<int, string>
     */
    public static function kanal(object $notifiable, string $kategori): array
    {
        $kanal = ['database'];

        if ($notifiable instanceof User && self::emailDiizinkan($notifiable, $kategori)) {
            $kanal[] = 'mail';
        }

        return $kanal;
    }

    public static function emailDiizinkan(User $pengguna, string $kategori): bool
    {
        if (in_array($kategori, self::WAJIB, true)) {
            return true;
        }

        // Kolom kosong berarti SEMUA MENYALA — pengguna lama tidak boleh
        // kehilangan surat hanya karena ada kolom baru yang masih null.
        return (bool) ($pengguna->preferensi_notifikasi[$kategori] ?? true);
    }

    /**
     * Daftar kategori beserta keadaannya, untuk halaman preferensi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pilihanPreferensi(User $pengguna): array
    {
        $hasil = [];

        foreach (self::KATEGORI as $kunci => $label) {
            $wajib = in_array($kunci, self::WAJIB, true);

            $hasil[] = [
                'kunci' => $kunci,
                'label' => $label,
                'keterangan' => self::KETERANGAN[$kunci] ?? '',
                'ikon' => self::ikon($kunci),
                'wajib' => $wajib,
                'email' => $wajib || self::emailDiizinkan($pengguna, $kunci),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $pilihan
     */
    public static function simpanPreferensi(User $pengguna, array $pilihan): void
    {
        $bersih = [];

        foreach (array_keys(self::KATEGORI) as $kategori) {
            // Kategori wajib SELALU tersimpan menyala: kalau nilainya bisa
            // ditulis false, aturannya tinggal di dua tempat dan cepat atau
            // lambat keduanya berbeda.
            $bersih[$kategori] = in_array($kategori, self::WAJIB, true)
                ? true
                : (bool) ($pilihan[$kategori] ?? false);
        }

        $pengguna->preferensi_notifikasi = $bersih;
        $pengguna->save();
    }

    /* ------------------------------------------------------------------ */
    /* Penerimaan di aplikasi                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Daftar notifikasi dalam aplikasi, terbaru di atas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(User $pengguna, ?string $kategori = null, int $batas = 60): array
    {
        return $pengguna->notifications()
            ->when($kategori, function ($q) use ($kategori): void {
                // Saringan kategori dibaca dari isi JSON-nya. Basis data kita
                // MySQL/SQLite, dan keduanya paham `->>`.
                $q->where('data->kategori', $kategori);
            })
            ->limit($batas)
            ->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'kategori' => $n->data['kategori'] ?? '',
                'label_kategori' => self::label($n->data['kategori'] ?? ''),
                'ikon' => $n->data['ikon'] ?? self::ikon($n->data['kategori'] ?? ''),
                'judul' => $n->data['judul'] ?? 'Notifikasi',
                'pesan' => $n->data['pesan'] ?? '',
                'tautan' => $n->data['tautan'] ?? null,
                'dibaca' => $n->read_at !== null,
                'waktu' => $n->created_at?->translatedFormat('d F Y, H:i'),
            ])->all();
    }

    public static function belumDibaca(User $pengguna): int
    {
        return $pengguna->unreadNotifications()->count();
    }

    public static function jumlahPerKategori(User $pengguna): array
    {
        $hasil = array_fill_keys(array_keys(self::KATEGORI), 0);

        foreach ($pengguna->notifications()->get() as $n) {
            $kategori = $n->data['kategori'] ?? '';

            if (array_key_exists($kategori, $hasil)) {
                $hasil[$kategori]++;
            }
        }

        return $hasil;
    }

    /**
     * Tandai satu notifikasi sudah dibaca.
     *
     * Mengembalikan true hanya bila notifikasi itu MEMANG milik pengguna ini —
     * memeriksa kepemilikan lewat relasi, bukan lewat id saja, supaya id
     * notifikasi orang lain tidak bisa ditandai dari sini.
     */
    public static function tandaiDibaca(User $pengguna, string $id): bool
    {
        $notifikasi = $pengguna->notifications()->whereKey($id)->first();

        if ($notifikasi === null) {
            return false;
        }

        $notifikasi->markAsRead();

        return true;
    }

    public static function tandaiSemuaDibaca(User $pengguna): int
    {
        return $pengguna->unreadNotifications()->update(['read_at' => now()]);
    }
}
