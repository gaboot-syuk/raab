<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perubahan status keanggotaan.
     *
     * Tidak ada status yang berubah tanpa jejak — termasuk kader → alumni,
     * penonaktifan, dan pemulihan status.
     */
    public function up(): void
    {
        Schema::create('member_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            $table->string('status_lama', 24)->nullable();
            $table->string('status_baru', 24);
            $table->string('jalur_lama', 16)->nullable();
            $table->string('jalur_baru', 16)->nullable();

            $table->text('alasan')->nullable();
            $table->unsignedBigInteger('diubah_oleh')->nullable();

            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_status_histories');
    }
};
