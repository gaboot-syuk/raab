<?php

namespace App\Http\Controllers\Anggota;

use App\Http\Controllers\Controller;
use App\Services\Pengumuman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;

/**
 * Pengumuman di area anggota.
 *
 * Isinya SEMUA pengumuman yang menjadi hak penonton: yang bertipe publik
 * maupun internal, selama audiensnya cocok. Penyaringan terjadi di layanan,
 * bukan di tampilan — yang tidak berhak tidak pernah sampai ke peramban.
 */
class PengumumanController extends Controller
{
    public function __construct(private Pengumuman $pengumuman) {}

    public function index(Request $request): ResponsInertia
    {
        $pengguna = $request->user();

        $daftar = $this->pengumuman->untuk($pengguna)
            ->map(fn ($p): array => [
                'id' => $p->id,
                'slug' => $p->slug,
                'judul' => $p->judulTeks(),
                'isi' => $p->isiTeks(),
                'label_tipe' => $p->labelTipe(),
                'tipe' => $p->tipe,
                'audiens_teks' => $p->audiensTeks(),
                'is_pinned' => (bool) $p->is_pinned,
                'publish_at_teks' => $p->publish_at?->translatedFormat('d F Y, H:i'),
                'expire_at_teks' => $p->expire_at?->translatedFormat('d F Y, H:i'),
                'label_waktu' => $p->labelWaktu(),
            ])->all();

        return Inertia::render('Anggota/Pengumuman', [
            'daftar' => $daftar,
            'total' => count($daftar),
            'catatan' => 'Di sini kamu melihat semua pengumuman yang menjadi hakmu — publik maupun internal. Pengumuman yang masa berlakunya sudah lewat tidak ditampilkan lagi, tetapi tidak dihapus: pengurus masih bisa membukanya.',
        ]);
    }
}
