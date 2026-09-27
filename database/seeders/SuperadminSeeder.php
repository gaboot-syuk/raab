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

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator RAAB',
                'password' => Hash::make($kataSandi),
                'status' => User::STATUS_AKTIF,
                'locale' => 'id',
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([Role::findByName('superadmin', 'web')]);

        $this->command?->info("Superadmin siap — masuk dengan: {$email} / {$kataSandi}");
    }
}
