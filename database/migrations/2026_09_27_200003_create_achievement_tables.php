<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prestasi kader.
     *
     * DUA TABEL: kategori diatur pengurus, prestasinya diajukan kader.
     *
     * Prestasi yang DIKLAIM KADER BELUM TENTANG dianggap benar. Ia berstatus
     * `diajukan` sampai Sekretaris atau Konten Manager memeriksa sertifikatnya.
     * Tanpa langkah itu, siapa pun bisa menuliskan dirinya juara nasional dan
     * langsung mendapat poin serta tayang di halaman publik rayon.
     */
    public function up(): void
    {
        Schema::create('achievement_categories', function (Blueprint $table) {
            $table->id();

            $table->string('kode', 40)->unique();
            $table->json('nama');
            $table->json('keterangan')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('achievement_category_id')->nullable()
                ->constrained('achievement_categories')->nullOnDelete();

            $table->json('judul');
            $table->json('deskripsi')->nullable();

            $table->string('penyelenggara', 190)->nullable();

            // rayon|kampus|regional|nasional|internasional
            $table->string('tingkat', 24)->index();

            // juara_1|juara_2|juara_3|harapan|finalis|peserta
            $table->string('peringkat', 24)->index();

            // Tanggal perolehan. Dipakai juga untuk menentukan periode poin.
            $table->date('tanggal');

            // Sertifikat/piagam. Bukti inilah yang diperiksa pengurus.
            $table->unsignedBigInteger('sertifikat_media_id')->nullable();
            $table->string('tautan_bukti', 500)->nullable();

            // diajukan|terverifikasi|ditolak
            $table->string('status', 24)->default('diajukan')->index();

            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('diverifikasi_pada')->nullable();
            $table->string('catatan_verifikasi', 500)->nullable();

            // Unggulan hanya boleh untuk prestasi yang SUDAH terverifikasi —
            // aturan itu ditegakkan di layanan, bukan di sini.
            $table->boolean('unggulan')->default(false)->index();

            // Sakelar privasi milik kader: prestasi boleh tidak ditayangkan
            // di halaman publik walau sudah terverifikasi.
            $table->boolean('tampil_publik')->default(true)->index();

            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'tingkat']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('achievement_categories');
    }
};
