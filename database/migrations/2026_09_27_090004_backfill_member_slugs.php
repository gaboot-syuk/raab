<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Isi slug untuk anggota yang sudah ada sebelum kolomnya ditambahkan.
     *
     * Dilakukan di sini (bukan lewat model) agar setiap lingkungan yang
     * menjalankan migrasi ini mendapat hasil yang sama, tanpa bergantung pada
     * keadaan model di masa depan.
     */
    public function up(): void
    {
        $sudahAda = [];

        foreach (DB::table('members')->orderBy('id')->get(['id', 'nama_lengkap', 'slug']) as $baris) {
            $dasar = Str::slug((string) $baris->nama_lengkap);

            if ($dasar === '') {
                $dasar = 'anggota';
            }

            $slug = $dasar;
            $urutan = 2;

            while (in_array($slug, $sudahAda, true)) {
                $slug = $dasar.'-'.$urutan;
                $urutan++;
            }

            $sudahAda[] = $slug;

            if (blank($baris->slug)) {
                DB::table('members')->where('id', $baris->id)->update(['slug' => $slug]);
            }
        }
    }

    public function down(): void
    {
        // Slug yang sudah diisi tidak perlu dikosongkan lagi.
    }
};
