<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kategori artikel bawaan rayon.
     *
     * Nama sengaja generik agar pengurus bisa mengganti/menambah lewat panel.
     */
    public function up(): void
    {
        $daftar = [
            ['Kegiatan', 'Activities', 1],
            ['Kaderisasi', 'Cadre Training', 2],
            ['Keilmuan', 'Scholarship', 3],
            ['Sosial & Advokasi', 'Social & Advocacy', 4],
            ['Prestasi', 'Achievements', 5],
            ['Literasi', 'Literacy', 6],
        ];

        foreach ($daftar as [$id, $en, $urutan]) {
            $slugId = \Illuminate\Support\Str::slug($id);
            $slugEn = \Illuminate\Support\Str::slug($en);

            // Kategori belum punya model seeder khusus; dibuat langsung agar
            // migrasi ini tetap aman dijalankan berulang.
            $sudahAda = DB::table('article_categories')
                ->where('slug->id', $slugId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            DB::table('article_categories')->insert([
                'nama' => json_encode(['id' => $id, 'en' => $en], JSON_UNESCAPED_UNICODE),
                'slug' => json_encode(['id' => $slugId, 'en' => $slugEn], JSON_UNESCAPED_UNICODE),
                'urutan' => $urutan,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('article_categories')->truncate();
    }
};
