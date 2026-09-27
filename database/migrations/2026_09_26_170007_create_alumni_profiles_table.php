<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil tambahan untuk anggota yang sudah berstatus alumni.
     *
     * Dipakai direktori alumni (Fase 4) dan penawaran menjadi mentor/pemateri.
     * Kolom `kontak_publik` mengatur bagian mana yang boleh tampil di direktori.
     */
    public function up(): void
    {
        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('tahun_lulus')->nullable()->index();
            $table->string('instansi', 190)->nullable();
            $table->string('jabatan', 160)->nullable();
            $table->string('bidang', 160)->nullable()->index();
            $table->string('kota_domisili', 120)->nullable()->index();

            $table->json('kontak_publik')->nullable();   // {"telepon": true, "email": false, "instansi": true}

            $table->boolean('bersedia_mentor')->default(false)->index();
            $table->text('topik_mentor')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_profiles');
    }
};
