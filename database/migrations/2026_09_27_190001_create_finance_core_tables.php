<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inti keuangan: akun kas, kategori, transaksi, dan anggaran (RKAT).
     *
     * SALDO TIDAK PERNAH DIUBAH LANGSUNG. `saldo_berjalan` hanya bergerak lewat
     * `finance_transactions` yang DIKONFIRMASI (lihat App\Services\Kas). Setiap
     * pergerakan menyimpan `saldo_sebelum` & `saldo_sesudah` supaya angka lama
     * tetap dapat diaudit walau akunnya sudah berubah berkali-kali.
     *
     * TRANSAKSI TIDAK PERNAH DIHAPUS — hanya di-void disertai alasan. Menghapus
     * baris akan menghilangkan jejak, dan itu justru yang tidak boleh terjadi.
     */
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('kode', 40)->unique();
            $table->json('nama');
            $table->json('keterangan')->nullable();

            $table->string('jenis', 24)->default('utama')->index();  // utama|kegiatan|lain

            // Saldo saat akun dibuat; tidak pernah berubah setelahnya.
            $table->bigInteger('saldo_awal')->default(0);

            // Saldo terkini — hanya berubah lewat transaksi terkonfirmasi.
            $table->bigInteger('saldo_berjalan')->default(0);

            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();
        });

        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();

            // Kategori bertingkat: "Belanja" → "Belanja ATK".
            $table->foreignId('parent_id')->nullable()->constrained('finance_categories')->nullOnDelete();

            $table->string('kode', 40)->unique();
            $table->json('nama');
            $table->json('keterangan')->nullable();

            $table->string('jenis', 16)->index();  // masuk|keluar

            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();

            $table->index(['parent_id', 'jenis']);
        });

        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();

            $table->string('nomor_voucher', 40)->unique();
            $table->date('tanggal')->index();

            $table->string('jenis', 16)->index();  // masuk|keluar
            $table->bigInteger('jumlah');

            $table->string('keterangan', 500);

            // Asal dana — dipakai laporan "per sumber".
            $table->string('sumber', 24)->default('lain')->index();
            // iuran|hibah|donasi|dana_kegiatan|usaha|lain

            // Tautan opsional ke kegiatan, anggaran, iuran, atau hibah.
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('budget_id')->nullable();
            $table->foreignId('due_payment_id')->nullable();
            $table->foreignId('donation_id')->nullable();

            $table->unsignedBigInteger('bukti_media_id')->nullable();

            $table->string('status', 24)->default('draft')->index();  // draft|terkonfirmasi|void

            // Jejak audit: siapa mencatat, mengonfirmasi, dan mem-void.
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dikonfirmasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dikonfirmasi_pada')->nullable();
            $table->foreignId('void_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_pada')->nullable();
            $table->string('void_alasan', 500)->nullable();

            // Angka akun tepat sebelum & sesudah transaksi ini dikonfirmasi.
            $table->bigInteger('saldo_sebelum')->nullable();
            $table->bigInteger('saldo_sesudah')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status', 'tanggal']);
            $table->index(['jenis', 'sumber']);
        });

        // Anggaran (RKAT) per periode kepengurusan, per kegiatan, atau per kategori.
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();

            $table->json('nama');
            $table->json('keterangan')->nullable();

            $table->string('jenis', 16)->default('keluar')->index();  // masuk|keluar
            $table->bigInteger('jumlah_direncanakan')->default(0);

            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();

            $table->index(['period_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('finance_accounts');
    }
};
