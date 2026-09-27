<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penugasan pengurus pada suatu periode.
     *
     * `member_id` boleh kosong: pengurus dapat ditunjuk dengan nama manual
     * (mis. dosen pembina atau tokoh yang bukan anggota terdaftar).
     */
    public function up(): void
    {
        Schema::create('position_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();

            $table->string('nama_manual', 160)->nullable();
            $table->string('keterangan', 190)->nullable();   // mis. "Plt. hingga Munas"

            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);

            $table->timestamps();

            $table->index(['period_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_assignments');
    }
};
