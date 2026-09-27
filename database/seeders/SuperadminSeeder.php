<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Akun Superadmin (Ketua Pengurus).
 *
 * Kredensial diambil dari .env bila tersedia, dengan nilai bawaan
 * untuk pengembangan. WAJIB diganti sebelum dipakai di produksi.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('SEED_SUPERADMIN_EMAIL', 'ketua@raab.test'));
        $kataSandi = (string) env('SEED_SUPERADMIN_PASSWORD', 'rahasia123');

        /*
         * Kosong diperiksa lebih dulu, dan ini bukan kehati-hatian berlebihan.
         *
         * "Tidak diisi" dan "dibiarkan kosong" berakhir BEDA. Bila variabelnya
         * tidak ada sama sekali, env() memakai nilai bawaan di atas. Tetapi
         * hosting — Render termasuk — sering menyimpan variabel yang dibiarkan
         * kosong sebagai string kosong, dan env() mengembalikan string kosong
         * itu, bukan nilai bawaannya.
         *
         * Akibatnya pernah terjadi sungguhan: akun superadmin terbuat dengan
         * email kosong dan kata sandi kosong. Akunnya ada, situsnya menyala,
         * tetapi tidak ada seorang pun yang bisa masuk — dan tanpa akses shell,
         * tidak ada cara memperbaikinya dari dalam.
         *
         * Lebih baik tidak ada akun sama sekali, dengan peringatan yang jelas,
         * daripada akun yang tidak bisa dipakai.
         */
        if ($email === '' || $kataSandi === '') {
            $this->command?->warn(
                'SEED_SUPERADMIN_EMAIL / SEED_SUPERADMIN_PASSWORD KOSONG — akun superadmin TIDAK dibuat. '.
                'Variabel yang dibiarkan kosong BUKAN berarti \'pakai nilai bawaan\'. '.
                'Isi keduanya di panel hosting, lalu deploy ulang dengan APP_JALANKAN_SEED=true.'
            );

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        // Kata sandi HANYA dipasang saat akunnya belum ada.
        //
        // Seeder ini ikut berjalan pada penyebaran berikutnya. Kalau kata
        // sandinya ditimpa setiap kali, sandi yang sudah diganti pengurus akan
        // kembali ke nilai di .env tanpa pemberitahuan — dan nilai itu
        // tertulis di tempat yang mungkin terbaca orang lain.
        if (! $user->exists) {
            $user->password = Hash::make($kataSandi);
        }

        $user->name = 'Administrator RAAB';
        $user->status = User::STATUS_AKTIF;
        $user->locale = 'id';
        $user->email_verified_at = now();
        $user->save();

        $user->syncRoles([Role::findByName('superadmin', 'web')]);

        // Kata sandinya sengaja TIDAK dicetak: log hosting dapat dibaca lebih
        // banyak orang daripada yang seharusnya.
        $this->command?->info($user->wasRecentlyCreated
            ? "Superadmin dibuat — masuk dengan {$email} dan kata sandi dari SEED_SUPERADMIN_PASSWORD."
            : "Superadmin {$email} sudah ada — kata sandinya TIDAK diubah.");
    }
}
