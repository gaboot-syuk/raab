<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat seluruh permission dan empat role organisasi.
 *
 * Role keanggotaan (kader aktif / alumni) TIDAK dibuat di sini, karena
 * keduanya berupa status pada tabel members — lihat docs/02-role-permission.md.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $peta = $this->petaIzin();

        // Memakai firstOrCreate (bukan findOrCreate) supaya seeder aman dijalankan ulang:
        // findOrCreate membaca cache permission yang bisa basi dan memicu galat duplikat.
        $izinTersimpan = [];

        foreach ($peta as $daftar) {
            foreach ($daftar as $izin) {
                $izinTersimpan[$izin] = Permission::query()->firstOrCreate([
                    'name' => $izin,
                    'guard_name' => 'web',
                ]);
            }
        }

        $registrar->forgetCachedPermissions();

        $semuaIzin = collect($peta)->flatten()->unique()->values()->all();

        $roleDanGrup = [
            'superadmin' => ['*'],
            'sekretaris' => [
                'dasbor', 'pengguna_dasar', 'keanggotaan', 'verifikasi', 'organisasi',
                'event', 'kegiatan', 'prestasi', 'pengumuman', 'inventaris',
                'perpustakaan', 'peminjaman', 'aspirasi', 'laporan', 'media_unggah',
                // Berita acara adalah tugas Sekretaris, tetapi TIDAK diberi izin
                // articles.publish — agar ia tidak dapat menerbitkan artikel
                // biasa dengan melewati alur review Konten Manager.
                'berita_acara',
            ],
            'bendahara' => [
                'dasbor', 'keuangan', 'donasi', 'laporan', 'media_unggah', 'pengumuman_keuangan',
                'anggota_ringkas',
            ],
            'konten_manager' => [
                'dasbor', 'publikasi', 'halaman', 'media', 'galeri', 'prestasi',
                'pengumuman', 'laporan', 'unit_konten',
            ],
        ];

        foreach ($roleDanGrup as $nama => $grup) {
            $role = Role::query()->firstOrCreate(['name' => $nama, 'guard_name' => 'web']);

            $namaIzin = $grup === ['*']
                ? $semuaIzin
                : collect($grup)
                    ->flatMap(fn (string $kunci): array => $peta[$kunci] ?? [])
                    ->unique()
                    ->values()
                    ->all();

            $role->syncPermissions(
                collect($namaIzin)->map(fn (string $izin) => $izinTersimpan[$izin])->all()
            );
        }

        $registrar->forgetCachedPermissions();

        $this->command?->info('Role & permission siap: '.count($semuaIzin).' izin, '.count($roleDanGrup).' role.');
    }

    /**
     * Katalog permission per kelompok.
     *
     * @return array<string, array<int, string>>
     */
    private function petaIzin(): array
    {
        return [
            'dasbor' => [
                'dashboard.view',
                'dashboard.view-finance-summary',
                'dashboard.view-membership-summary',
            ],

            'pengguna_dasar' => [
                'periods.view',
                'units.view',
                'positions.view',
                'members.view',
            ],

            'keanggotaan' => [
                'members.view',
                'members.verify',
                'members.update',
                'members.export',
                'members.change-status',
                'members.view-sensitive',
                'members.delete',
                'alumni-profiles.view',
                'alumni-profiles.update',
                'alumni-profiles.verify',
                'alumni-profiles.export',
                'member-cards.issue',
                'member-cards.revoke',
            ],

            'verifikasi' => [
                'verifications.view',
                'verifications.approve',
                'verifications.reject',
                'verifications.request-revision',
            ],

            'organisasi' => [
                'periods.view',
                'units.view',
                'units.create',
                'units.update',
                'units.delete',
                'positions.view',
                'positions.manage',
                'assignments.manage',
                'mentors.view',
            ],

            // Membuat/mengubah/menghapus PERIODE hanya milik Superadmin.
            // Periode adalah kerangka seluruh data kepengurusan: salah menandai
            // periode aktif membuat struktur organisasi tampil keliru.
            'periode_kelola' => [
                'periods.create',
                'periods.update',
                'periods.delete',
                'periods.activate',
            ],

            'publikasi' => [
                'articles.view',
                'articles.create',
                'articles.update',
                'articles.delete',
                'articles.review',
                'articles.publish',
                'articles.publish-berita-acara',
                'articles.feature',
                'articles.schedule',
                'article-categories.manage',
                'tags.manage',
            ],

            // Berita acara: Sekretaris menulis & menerbitkannya sendiri.
            // Sengaja TERPISAH dari kelompok 'publikasi' supaya tidak sekaligus
            // memberi hak menerbitkan artikel biasa.
            'berita_acara' => [
                'articles.view',
                'articles.create',
                'articles.update',
                'articles.publish-berita-acara',
            ],

            'halaman' => [
                'pages.manage',
                'sliders.manage',
                'social-links.manage',
                'settings.site-manage',
                'messages.view',
                'messages.reply',
                'contacts.view',
                'contacts.reply',
            ],

            'media' => [
                'media.view',
                'media.upload',
                'media.update',
                'media.delete',
            ],

            // Sekretaris & Bendahara hanya boleh mengunggah berkas pendukung.
            'media_unggah' => [
                'media.view',
                'media.upload',
            ],

            'galeri' => [
                'galleries.manage',
                'unit-agendas.manage',
                'translations.manage',
            ],

            /*
             * ISI halaman Biro & LSO — halaman unitnya sendiri.
             *
             * Sengaja TERPISAH dari kelompok 'organisasi' di atas, karena
             * kelompok itu juga membawa units.create, units.delete,
             * positions.manage, dan assignments.manage. Memberikan seluruhnya
             * kepada Konten Manager berarti ia boleh MEMBENTUK ULANG struktur
             * organisasi, padahal yang dibutuhkan hanya mengisi isinya.
             *
             * Jadi: boleh membuka halaman unit dan mengganti nama, singkatan,
             * deskripsi, warna, serta status tayangnya. TIDAK boleh membuat
             * atau menghapus unit, menata jabatan, atau memindahkan pengurus.
             *
             * Galeri dan agenda unit tidak perlu dicantumkan di sini — keduanya
             * sudah masuk kelompok 'galeri' yang memang sudah dipegang Konten
             * Manager. Daftar ini hanya menutup celah yang tersisa.
             */
            'unit_konten' => [
                'units.view',
                'units.update',
            ],

            // Daftar kesediaan mentor/pemateri — dibutuhkan Sekretaris untuk
            // menyusun pemateri kegiatan.
            'mentor' => [
                'mentors.view',
            ],

            'event' => [
                'events.view',
                'events.create',
                'events.update',
                'events.delete',
                'events.open-close',
                'registrations.view',
                'registrations.verify',
                'registrations.reject',
                'registrations.export',
                'registrations.mark-attendance',
                'events.promote-to-member',
            ],

            'kegiatan' => [
                'activities.view',
                'activities.manage',
                'attendances.view',
                'attendances.manage',
                'attendances.open-qr',
                'points.view',
                'points.adjust',
            ],

            'prestasi' => [
                'achievements.view',
                'achievements.verify',
                'achievements.reject',
                'achievements.feature',
                'achievements.delete',
                'achievement-categories.manage',
            ],

            'pengumuman' => [
                'announcements.view',
                'announcements.manage',
                'documents.view',
                'documents.manage',
            ],

            'pengumuman_keuangan' => [
                'documents.view',
            ],

            'inventaris' => [
                'inventory.categories.manage',
                'inventory.items.view',
                'inventory.items.manage',
                'inventory.movements.manage',
                'inventory.export',
            ],

            'perpustakaan' => [
                'library.books.view',
                'library.books.manage',
                'library.copies.manage',
                'library.reservations.view',
                'library.reservations.manage',
            ],

            'peminjaman' => [
                'loans.view',
                'loans.approve',
                'loans.reject',
                'loans.handover',
                'loans.receive-return',
                'loans.extend-approve',
                'loans.request-for-others',
                'loans.manage',
                'loans.report-damage',
            ],

            'keuangan' => [
                'finance.accounts.manage',
                'finance.categories.manage',
                'finance.transactions.view',
                'finance.transactions.manage',
                'finance.transactions.verify',
                'finance.void',
                'dues.manage',
                'dues.payments.verify',
                'dues.payments.manage',
                'budgets.manage',
                'finance.reports.view',
                'finance.export',
            ],

            'donasi' => [
                'donations.view',
                'donations.verify',
                'donations.manage',
                'donations.export',
            ],

            'anggota_ringkas' => [
                'members.view',
            ],

            'aspirasi' => [
                'aspirations.view',
                'aspirations.reply',
                'aspirations.publish',
                'aspirations.close',
                'aspirations.view-identity',
            ],

            'laporan' => [
                'reports.generate',
                'reports.export',
            ],

            // Pengelolaan akun pengurus & peran — hanya Superadmin.
            'sistem' => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'roles.manage',
            ],
        ];
    }
}
