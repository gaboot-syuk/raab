<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferensi notifikasi email per kategori.
 *
 * DIKOSONGKAN = SEMUA MENYALA. Itu disengaja: kolom ini lahir setelah
 * notifikasinya sudah berjalan, jadi pengguna lama tidak boleh tiba-tiba
 * berhenti menerima surat hanya karena ada kolom baru yang masih null.
 *
 * Notifikasi DALAM APLIKASI tidak diatur di sini dan tidak pernah bisa
 * dimatikan — lihat `App\Services\Notifikasi`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('preferensi_notifikasi')->nullable()->after('preferensi_tema');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferensi_notifikasi');
        });
    }
};
