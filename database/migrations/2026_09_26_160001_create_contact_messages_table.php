<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesan dari formulir "Kontak Rayon" pada situs publik.
     *
     * Pesan dapat dikirim tanpa akun, sehingga identitas pengirim disimpan
     * apa adanya. Nomor IP & user agent disimpan untuk keperluan penanganan
     * spam, bukan untuk dipublikasikan.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 120);
            $table->string('email', 190);
            $table->string('telepon', 40)->nullable();
            $table->string('asal', 190)->nullable();      // instansi / kampus / komunitas
            $table->string('jenis', 40)->default('umum')->index();  // umum|kerjasama|undangan|lainnya
            $table->string('subjek', 190);
            $table->text('pesan');

            $table->string('status', 24)->default('baru')->index(); // baru|dibaca|dibalas|arsip
            $table->text('catatan_internal')->nullable();
            $table->unsignedBigInteger('dibalas_oleh')->nullable()->index();
            $table->timestamp('dibalas_pada')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
