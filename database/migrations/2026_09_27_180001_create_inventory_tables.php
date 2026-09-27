<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventaris aset rayon.
     *
     * JUMLAH TIDAK PERNAH DIUBAH LANGSUNG. Setiap perubahan stok harus lewat
     * `inventory_movements` — di situlah barang masuk, keluar, rusak, hilang, dan
     * perbaikan dicatat beserta penanggung jawabnya. Kolom `jumlah` pada tabel
     * aset hanyalah angka terkini hasil penjumlahan mutasi.
     */
    public function up(): void
    {
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();

            $table->json('nama');
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->nullable()->constrained('inventory_categories')->nullOnDelete();

            $table->string('kode', 60)->unique();
            $table->json('nama');
            $table->json('keterangan')->nullable();

            $table->string('satuan', 24)->default('buah');

            $table->integer('jumlah')->default(0);
            $table->unsignedInteger('jumlah_minimum')->default(0);

            $table->string('kondisi', 24)->default('baik')->index();  // baik|rusak_ringan|rusak_berat
            $table->string('lokasi', 160)->nullable();
            $table->unsignedBigInteger('nilai')->default(0);          // nilai perolehan (Rp)
            $table->unsignedBigInteger('foto_media_id')->nullable();

            // Hanya barang bertanda ini yang tampil di /inventaris.
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();

            $table->index(['category_id', 'aktif']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();

            $table->string('jenis', 24)->index();   // masuk|keluar|penyesuaian|rusak|hilang|perbaikan
            $table->integer('jumlah');              // positif menambah, negatif mengurangi

            // Angka sebelum & sesudah disimpan agar riwayat tetap dapat dibaca
            // walau asetnya sudah berubah berkali-kali.
            $table->integer('jumlah_sebelum');
            $table->integer('jumlah_sesudah');

            /*
             * Penanggung jawab. WAJIB diisi untuk barang rusak & hilang —
             * ditegakkan di layanan, bukan di basis data, supaya pesannya bisa
             * menjelaskan alasannya kepada pengurus.
             */
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('penanggung_jawab_nama', 160)->nullable();

            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->text('catatan')->nullable();
            $table->dateTime('terjadi_pada')->index();

            $table->timestamps();

            $table->index(['item_id', 'terjadi_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
    }
};
