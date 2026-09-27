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
        $email = env('SEED_SUPERADMIN_EMAIL', 'ketua@raab.test');
        $kataSandi = env('SEED_SUPERADMIN_PASSWORD', 'rahasia123');

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
