<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Halaman statis yang dikelola Konten Manager:
     * Sejarah, Visi & Misi, Sambutan, dan halaman bebas lainnya.
     *
     * Kolom slug/judul/ringkasan/konten/seo_* bertipe JSON karena
     * menyimpan dua bahasa sekaligus (id + en) — lihat docs/10-lokalisasi-bilingual.md.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            // Halaman tetap punya kunci agar mudah dipanggil (sejarah, visi_misi, sambutan).
            // Halaman bebas membiarkan kunci kosong.
            $table->string('kunci', 50)->nullable()->unique();

            $table->json('slug');
            $table->json('judul');
            $table->json('ringkasan')->nullable();
            $table->json('konten');
            $table->json('seo_judul')->nullable();
            $table->json('seo_deskripsi')->nullable();

            $table->string('tipe', 30)->default('statis')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('terbit_pada')->nullable();

            // Tanpa constraint ke tabel media agar urutan migrasi tidak bergantung
            // pada paket media library. Constraint ditambahkan pada migrasi terpisah.
            $table->unsignedBigInteger('media_id')->nullable()->index();

            $table->unsignedBigInteger('dibuat_oleh')->nullable()->index();
            $table->unsignedBigInteger('diperbarui_oleh')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
