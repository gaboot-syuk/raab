<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jabatan dalam kepengurusan rayon.
     *
     * `level` menentukan kedalaman pada bagan struktur dan `urutan` menentukan
     * urutan tampil dalam satu tingkat (Fase 4).
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 140);
            $table->unsignedTinyInteger('level')->default(1);   // 1 = pimpinan, 2 = pengurus, 3 = kepala biro
            $table->unsignedInteger('urutan')->default(0);

            // Jabatan dapat menempel pada unit tertentu (mis. "Kepala Biro Kaderisasi").
            $table->foreignId('unit_id')->nullable()->constrained('organisation_units')->nullOnDelete();

            $table->boolean('rangkap_diizinkan')->default(false);
            $table->boolean('aktif')->default(true)->index();

            $table->timestamps();

            $table->unique(['nama', 'unit_id']);
            $table->index(['level', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
