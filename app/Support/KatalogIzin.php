<?php

namespace App\Support;

use Str;

/**
 * Label berbahasa Indonesia untuk kelompok izin (permission).
 *
 * Nama izin memakai pola "kelompok.aksi" (mis. "members.verify"). Kelompoknya
 * dipetakan ke label yang enak dibaca pengurus pada halaman Peran & Izin.
 */
class KatalogIzin
{
    /**
     * Label per kelompok izin.
     *
     * @var array<string, string>
     */
    public const KELOMPOK = [
        'dashboard' => 'Dasbor',
        'members' => 'Data Anggota',
        'alumni-profiles' => 'Profil Alumni',
        'member-cards' => 'Kartu Kader',
        'verifications' => 'Antrean Verifikasi',
        'periods' => 'Periode Kepengurusan',
        'units' => 'Biro & LSO',
        'positions' => 'Jabatan',
        'assignments' => 'Penugasan Pengurus',
        'articles' => 'Artikel & Publikasi',
        'berita_acara' => 'Berita Acara (Sekretaris)',
        'article-categories' => 'Kategori Artikel',
        'tags' => 'Tag Artikel',
        'pages' => 'Halaman Statis',
        'sliders' => 'Slider Beranda',
        'social-links' => 'Tautan Media Sosial',
        'settings' => 'Pengaturan Situs',
        'messages' => 'Pesan Masuk',
        'contacts' => 'Pesan Kontak',
        'media' => 'Pustaka Media',
        'galleries' => 'Galeri',
        'unit-agendas' => 'Agenda Unit',
        'translations' => 'Terjemahan',
        'events' => 'Event Mapaba & PKD',
        'registrations' => 'Peserta Event',
        'activities' => 'Kegiatan',
        'attendances' => 'Presensi',
        'books' => 'Katalog Buku',
        'library-loans' => 'Peminjaman Buku',
        'assets' => 'Inventaris Aset',
        'asset-mutations' => 'Mutasi Aset',
        'finance' => 'Keuangan',
        'dues' => 'Iuran Anggota',
        'budgets' => 'Anggaran',
        'reports' => 'Laporan',
        'achievements' => 'Prestasi',
        'points' => 'Poin Kontribusi',
        'announcements' => 'Pengumuman',
        'archives' => 'Arsip Dokumen',
        'aspirations' => 'Aspirasi',
        'donations' => 'Hibah & Donasi',
        'users' => 'Akun Pengurus',
        'roles' => 'Peran & Izin',
    ];

    /**
     * Label per izin bila perlu lebih spesifik daripada nama aksinya.
     *
     * @var array<string, string>
     */
    public const IZIN = [
        'dashboard.view' => 'Melihat dasbor',
        'members.verify' => 'Memverifikasi pendaftar',
        'members.change-status' => 'Mengubah status kader/alumni',
        'members.view-sensitive' => 'Melihat data sensitif (NIM, HP)',
        'settings.site-manage' => 'Mengubah pengaturan situs',
        'pages.manage' => 'Mengelola halaman statis',
        'messages.view' => 'Membaca pesan masuk',
        'users.view' => 'Melihat daftar akun pengurus',
        'users.create' => 'Membuat akun pengurus',
        'users.update' => 'Mengubah akun & perannya',
        'users.delete' => 'Menghapus akun pengurus',
        'roles.manage' => 'Mengatur izin per peran',
    ];

    public static function labelKelompok(string $prefiks): string
    {
        return self::KELOMPOK[$prefiks] ?? Str::headline($prefiks);
    }

    public static function labelIzin(string $izin): string
    {
        if (isset(self::IZIN[$izin])) {
            return self::IZIN[$izin];
        }

        $aksi = Str::after($izin, '.');

        return Str::ucfirst(str_replace('-', ' ', $aksi));
    }
}
