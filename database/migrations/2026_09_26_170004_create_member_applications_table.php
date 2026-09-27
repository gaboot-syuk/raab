<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan keanggotaan dari formulir /daftar.
     *
     * Dipisahkan dari tabel members supaya pendaftar yang ditolak atau diminta
     * memperbaiki data tetap tercatat, tanpa mengotori data anggota resmi.
     * Verifikasi email ditangani Fortify (kolom users.email_verified_at).
     */
    public function up(): void
    {
        Schema::create('member_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();

            $table->string('jalur', 16)->index();                        // kader | alumni
            $table->string('status', 24)->default('menunggu')->index();  // menunggu|disetujui|ditolak|perbaikan

            // Seluruh isian formulir disimpan apa adanya sebagai snapshot,
            // sehingga riwayat pengajuan tidak berubah bila anggota memperbarui profil.
            $table->json('data');

            $table->text('catatan_pengurus')->nullable();   // catatan untuk pemohon (alasan tolak/perbaikan)
            $table->text('catatan_internal')->nullable();   // catatan antar pengurus

            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamp('diproses_pada')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_applications');
    }
};
