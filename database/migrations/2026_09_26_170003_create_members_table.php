<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identitas keanggotaan seseorang — jantung seluruh modul berikutnya.
     *
     * Satu baris per pengguna. `status` dipakai untuk menentukan hak akses
     * (kader aktif boleh meminjam buku & presensi; alumni terbatas), sedangkan
     * peran kepengurusan tetap dipegang Spatie Permission.
     *
     * Data sensitif (NIM, telepon, email) tidak pernah ditampilkan di halaman
     * publik kecuali diizinkan pemiliknya lewat kolom `privasi`.
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nomor_anggota', 40)->nullable()->unique();

            $table->string('jalur', 16)->default('kader')->index();      // kader | alumni
            $table->string('status', 24)->default('menunggu')->index();  // menunggu|aktif|alumni|nonaktif|ditolak

            $table->string('nama_lengkap', 160);
            $table->string('nama_panggilan', 60)->nullable();
            $table->string('jenis_kelamin', 12)->nullable();             // laki_laki | perempuan
            $table->string('tempat_lahir', 120)->nullable();
            $table->date('tanggal_lahir')->nullable();

            $table->string('nim', 40)->nullable()->index();
            $table->string('fakultas', 160)->nullable();
            $table->string('program_studi', 160)->nullable();
            $table->unsignedSmallInteger('angkatan')->nullable()->index();

            $table->text('alamat')->nullable();
            $table->string('telepon', 40)->nullable();
            $table->string('email_kontak', 190)->nullable();

            $table->unsignedBigInteger('foto_media_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable()->index();

            $table->json('keahlian')->nullable();    // ["jurnalistik", "desain"]
            $table->json('sosmed')->nullable();      // {"instagram": "...", "linkedin": "..."}
            $table->json('privasi')->nullable();     // {"telepon": false, "email": false, ...}

            // Sidik jari berkas untuk mendeteksi pengajuan ganda saat impor.
            $table->string('sidik_data', 64)->nullable()->index();

            $table->unsignedBigInteger('diverifikasi_oleh')->nullable();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->text('catatan_verifikasi')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'jalur']);
            $table->index(['angkatan', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
