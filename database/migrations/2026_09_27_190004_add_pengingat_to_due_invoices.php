<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kapan terakhir anggota diingatkan soal tagihan ini.
     *
     * Pengingat dikirim MANUAL oleh Bendahara. Tanpa kolom ini, satu klik
     * ganda — atau dua pengurus menekan tombol yang sama — akan mengirim surat
     * pengingat berkali-kali ke anggota yang sama, dan yang paling mungkin
     * mengalaminya justru anggota yang sudah bayar.
     */
    public function up(): void
    {
        Schema::table('due_invoices', function (Blueprint $table) {
            $table->timestamp('pengingat_terakhir_pada')->nullable()->after('catatan');
            $table->unsignedInteger('pengingat_terkirim')->default(0)->after('pengingat_terakhir_pada');
        });
    }

    public function down(): void
    {
        Schema::table('due_invoices', function (Blueprint $table) {
            $table->dropColumn(['pengingat_terakhir_pada', 'pengingat_terkirim']);
        });
    }
};
