<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengumuman.
     *
     * TIGA SAKELAR YANG HARUS DIBEDAKAN, dan ketiganya sering tertukar:
     *
     *  - `tipe`            : publik atau internal. Menentukan apakah ia boleh
     *                        muncul di halaman publik `/pengumuman`.
     *  - `target_audience` : SIAPA yang boleh membacanya (publik/kader/alumni/
     *                        pengurus). Pengumuman internal bisa ditujukan
     *                        hanya ke pengurus.
     *  - `publish_at` / `expire_at` : KAPAN ia berlaku. Pengumuman tidak
     *                        dihapus saat kedaluwarsa — ia hanya berhenti
     *                        tayang, karena menghapusnya berarti kehilangan
     *                        jejak apa yang pernah diumumkan rayon.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 191)->unique();

            // Judul & isi disimpan dua bahasa (id, en).
            $table->json('judul');
            $table->json('isi');

            // publik|internal
            $table->string('tipe', 16)->default('internal')->index();

            // ["publik","kader","alumni","pengurus"] — minimal satu.
            $table->json('target_audience');

            // Disematkan di paling atas daftar.
            $table->boolean('is_pinned')->default(false)->index();

            $table->dateTime('publish_at')->index();
            $table->dateTime('expire_at')->nullable()->index();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Saringan harian: "yang tayang sekarang" disaring per tipe.
            $table->index(['tipe', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
