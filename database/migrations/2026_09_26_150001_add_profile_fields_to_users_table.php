<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tambahan pada tabel users:
     * - locale & preferensi_tema untuk bilingual + mode gelap
     * - phone/whatsapp untuk keperluan kontak pengurus
     * - status untuk menonaktifkan akun tanpa menghapus data
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('whatsapp', 30)->nullable()->after('phone');
            $table->string('locale', 5)->default('id')->after('password');
            $table->string('preferensi_tema', 10)->default('sistem')->after('locale');
            $table->string('status', 20)->default('aktif')->index()->after('preferensi_tema');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'whatsapp',
                'locale',
                'preferensi_tema',
                'status',
                'last_login_at',
            ]);
        });
    }
};
