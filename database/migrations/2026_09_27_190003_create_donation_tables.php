<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hibah & dukungan alumni — DANA, BARANG, dan JASA.
     *
     * Satu tabel untuk tiga jenis karena alurnya sama (diajukan → dijanjikan →
     * diterima), yang berbeda hanya cara penerimaannya:
     *  - dana   → melahirkan transaksi kas masuk (sumber `hibah`)
     *  - barang → melahirkan mutasi inventaris `masuk` (stok aset bertambah)
     *  - jasa   → dicatat sebagai kesediaan, boleh ditautkan ke kegiatan
     *
     * Kolom tautan ketiganya sengaja disediakan di sini supaya hubungan sebab
     * akibatnya dapat ditelusuri dari kedua arah (hibah → kas/inventaris).
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_hibah', 40)->unique();

            // Pemberi: anggota/alumni terdaftar, atau pihak luar (nama manual).
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('alumni_profile_id')->nullable()->constrained('alumni_profiles')->nullOnDelete();
            $table->string('nama_pemberi', 160);
            $table->string('kontak', 160)->nullable();

            $table->string('jenis', 16)->index();  // dana|barang|jasa

            $table->json('judul');
            $table->json('deskripsi')->nullable();

            $table->unsignedBigInteger('estimasi_nilai')->default(0);
            $table->unsignedBigInteger('nilai_diterima')->nullable();

            $table->date('tanggal_rencana')->nullable();
            $table->timestamp('diterima_pada')->nullable();

            $table->string('status', 24)->default('diajukan')->index();
            // diajukan|dijanjikan|diterima|diverifikasi|ditolak|dibatalkan

            // Identitas disembunyikan dari laporan internal biasa; hanya
            // Superadmin yang boleh melihat nama aslinya.
            $table->boolean('anonim')->default(false)->index();

            $table->string('alasan_tolak', 500)->nullable();
            $table->string('catatan_bendahara', 500)->nullable();

            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();

            // Hasil penerimaan, sesuai jenisnya.
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions')->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();

            // Kondisi barang saat diserahkan (khusus jenis `barang`).
            $table->string('kondisi_barang', 24)->nullable();

            $table->unsignedBigInteger('bukti_media_id')->nullable();

            $table->timestamps();

            $table->index(['status', 'jenis']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
