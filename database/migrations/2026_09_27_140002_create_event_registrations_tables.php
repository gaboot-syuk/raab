<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pendaftar event — TANPA akun.
     *
     * `email` unik per event: pendaftar ganda dengan email yang sama ditolak di
     * tingkat basis data, bukan hanya di validasi, supaya tidak ada celah saat
     * dua kiriman datang bersamaan.
     *
     * `sidik_data` menyimpan sidik jari pengiriman (email + telepon + nama yang
     * dinormalkan) untuk mendeteksi pendaftaran ulang dengan ejaan berbeda.
     */
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('kode_pendaftaran', 40)->unique();

            $table->string('nama_lengkap', 160);
            $table->string('email', 190);
            $table->string('telepon', 40)->nullable();
            $table->string('jenis_kelamin', 12)->nullable();
            $table->string('tempat_lahir', 120)->nullable();
            $table->date('tanggal_lahir')->nullable();

            $table->string('nim', 40)->nullable();
            $table->string('fakultas', 160)->nullable();
            $table->string('program_studi', 160)->nullable();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->string('instansi', 190)->nullable();   // sekolah/kampus/instansi asal
            $table->text('alamat')->nullable();

            $table->string('status', 24)->default('menunggu')->index();
            $table->text('catatan_peserta')->nullable();   // dari pendaftar
            $table->text('catatan_panitia')->nullable();   // dari panitia
            $table->boolean('hadir')->default(false);

            $table->string('sidik_data', 64)->nullable()->index();

            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();

            // Diisi saat peserta dipromosikan menjadi Kader Aktif.
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('dipromosikan_pada')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'email']);
            $table->index(['event_id', 'status']);
        });

        /*
         * Jawaban untuk kolom isian tambahan yang dibuat panitia.
         *
         * Disimpan di tabel terpisah (bukan kolom JSON) agar dapat disaring dan
         * diekspor seperti kolom lain, serta tidak terpengaruh bila panitia
         * mengubah susunan kolomnya.
         */
        Schema::create('event_registration_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_field_id')->constrained()->cascadeOnDelete();

            $table->text('nilai')->nullable();

            $table->timestamps();

            $table->unique(['event_registration_id', 'event_field_id'], 'answers_registration_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_answers');
        Schema::dropIfExists('event_registrations');
    }
};
