<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Iuran anggota: kategori, tagihan, dan pembayaran.
     *
     * Kategori iuran diatur Bendahara (anggota aktif / pengurus / alumni, dan
     * tambahan lain). Tagihan diterbitkan per kategori per periode.
     *
     * PEMBAYARAN TRANSFER TIDAK LANGSUNG LUNAS. Ia berstatus `menunggu` sampai
     * Bendahara memverifikasi buktinya. Pembayaran tunai yang dicatat Bendahara
     * langsung terverifikasi — karena uangnya sudah di tangan.
     */
    public function up(): void
    {
        // Nama tabel mengikuti nama model (DueCategory → due_categories).
        Schema::create('due_categories', function (Blueprint $table) {
            $table->id();

            $table->string('kode', 40)->unique();
            $table->json('nama');
            $table->json('keterangan')->nullable();

            // Siapa yang otomatis menerima tagihan kategori ini.
            $table->string('target_audiens', 24)->index();  // kader_aktif|pengurus|alumni|semua

            $table->unsignedBigInteger('nominal')->default(0);
            $table->string('periode', 16)->default('bulanan');  // bulanan|tahunan|sekali

            $table->unsignedInteger('urutan')->default(0);

            // Dinonaktifkan tanpa menghapus riwayat tagihan.
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();
        });

        Schema::create('due_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('due_category_id')->constrained('due_categories')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            // Label periode bebas, mis. "2026-09" atau "2026".
            $table->string('periode_label', 24)->index();

            $table->unsignedBigInteger('nominal')->default(0);
            $table->date('jatuh_tempo')->nullable();

            $table->string('status', 24)->default('belum')->index();
            // belum|menunggu|verifikasi|lunas|dibebaskan|ditolak

            // Menandai lunas tanpa pembayaran WAJIB disertai alasan.
            $table->string('dibebaskan_alasan', 500)->nullable();
            $table->foreignId('dibebaskan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan', 500)->nullable();

            $table->timestamps();

            // Satu tagihan per anggota per kategori per periode.
            $table->unique(['due_category_id', 'member_id', 'periode_label']);
        });

        Schema::create('due_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('due_invoice_id')->constrained('due_invoices')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->unsignedBigInteger('jumlah');
            $table->string('metode', 16)->index();  // tunai|transfer

            $table->unsignedBigInteger('bukti_media_id')->nullable();

            $table->string('status', 24)->default('menunggu')->index();
            // menunggu|terverifikasi|ditolak

            $table->string('catatan_pembayar', 500)->nullable();
            $table->string('catatan_bendahara', 500)->nullable();

            $table->timestamp('dibayar_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();

            // Transaksi kas yang lahir dari pembayaran ini (diisi saat verifikasi).
            $table->foreignId('transaction_id')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_payments');
        Schema::dropIfExists('due_invoices');
        Schema::dropIfExists('due_categories');
    }
};
