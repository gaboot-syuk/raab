<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tambahan Fase 4.
     *
     * - `members.slug`          → alamat publik profil kader (`/kader/{slug}`)
     *                             dan halaman prestasi (`/prestasi/kader/{slug}`).
     *                             Nomor anggota tidak dipakai karena memuat garis
     *                             miring dan lebih baik tidak dipajang di URL.
     * - `alumni_profiles.latitude/longitude` → titik pada peta sebaran alumni.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('slug', 190)->nullable()->unique()->after('nomor_anggota');
            $table->boolean('profil_publik')->default(false)->after('privasi')->index();
        });

        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('kota_domisili');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['slug', 'profil_publik']);
        });

        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
