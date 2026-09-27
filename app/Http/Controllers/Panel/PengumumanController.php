<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Pengumuman;
use App\Support\Audiens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as ResponsInertia;
use Symfony\Component\HttpFoundation\Response as ResponsHttp;

/**
 * Panel pengumuman.
 *
 * Dua izin dipisah tegas: `announcements.view` untuk membaca dan mengunduh
 * daftarnya, `announcements.manage` untuk mengubahnya. Orang yang boleh
 * membaca belum tentu boleh menulis.
 *
 * Galat dari layanan ditangkap SENDIRI, bukan lewat trait `jalankan()`: trait
 * itu membuang nilai kembalian aksinya, sedangkan di sini judul pengumuman
 * diperlukan untuk menyusun pesan yang menyebut apa yang barusan dibuat.
 */
class PengumumanController extends Controller
{
    public function __construct(private Pengumuman $pengumuman) {}

    public function index(Request $request): ResponsInertia
    {
        $saringan = $request->string('saringan')->toString();

        return Inertia::render('Panel/Pengumuman/Index', [
            'daftar' => $this->pengumuman->daftarPanel($saringan !== '' ? $saringan : null),
            'rekap' => $this->pengumuman->rekap(),
            'saringan' => $saringan,
            'pilihanTipe' => Announcement::TIPE,
            'pilihanAudiens' => Audiens::PILIHAN,
            'catatan' => 'Pengumuman yang sudah kedaluwarsa tidak dihapus, hanya berhenti tayang — supaya jejak apa yang pernah diumumkan rayon tetap ada. Pengumuman bertipe publik wajib menyertakan audiens "Umum", kalau tidak halaman publik akan menampilkannya tetapi tidak ada yang bisa membukanya.',
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        try {
            $pengumuman = $this->pengumuman->simpan($data, $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first())->withInput();
        }

        $kapan = $pengumuman->publish_at?->translatedFormat('d F Y, H:i') ?? 'sekarang';

        return back()->with('sukses', 'Pengumuman "'.$pengumuman->judulTeks().'" disimpan dan mulai tayang '.$kapan.'.');
    }

    public function perbarui(Request $request, Announcement $pengumuman): RedirectResponse
    {
        $data = $this->validasi($request);

        try {
            $this->pengumuman->perbarui($pengumuman, $data, $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first())->withInput();
        }

        return back()->with('sukses', 'Pengumuman "'.$pengumuman->judulTeks().'" diperbarui.');
    }

    public function sematkan(Request $request, Announcement $pengumuman): RedirectResponse
    {
        $data = $request->validate(['is_pinned' => ['required', 'boolean']]);

        $this->pengumuman->sematkan($pengumuman, (bool) $data['is_pinned'], $request->user());

        return back()->with('sukses', $data['is_pinned']
            ? 'Pengumuman disematkan di puncak daftar.'
            : 'Sematkan dilepas.');
    }

    public function hapus(Request $request, Announcement $pengumuman): RedirectResponse
    {
        $judul = $pengumuman->judulTeks();

        $this->pengumuman->hapus($pengumuman, $request->user());

        return back()->with('sukses', 'Pengumuman "'.$judul.'" dihapus. Isinya masih tersimpan di basis data bila perlu ditelusuri.');
    }

    /**
     * Ekspor CSV — bukan .xlsx, karena container tidak punya ekstensi zip yang
     * dibutuhkan pembuat xlsx.
     */
    public function ekspor(): ResponsHttp
    {
        $bersih = fn (?string $teks): string => '"'.str_replace('"', '""', (string) $teks).'"';

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Judul;Tipe;Audiens;Disematkan;Mulai Tayang;Berakhir;Keadaan;Dibuat\n";

        foreach (Announcement::query()->orderByDesc('publish_at')->limit(2000)->get() as $p) {
            $isi .= implode(';', [
                $bersih($p->judulTeks()),
                $bersih($p->labelTipe()),
                $bersih(implode(', ', $p->audiensTeks())),
                $bersih($p->is_pinned ? 'Ya' : 'Tidak'),
                $bersih($p->publish_at?->format('Y-m-d H:i') ?? '-'),
                $bersih($p->expire_at?->format('Y-m-d H:i') ?? '-'),
                $bersih($p->labelWaktu()),
                $bersih($p->created_at?->format('Y-m-d H:i') ?? '-'),
            ])."\n";
        }

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pengumuman.csv"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:190'],
            'isi' => ['required', 'string', 'max:8000'],
            'tipe' => ['required', Rule::in(array_keys(Announcement::TIPE))],
            'target_audience' => ['required', 'array', 'min:1'],
            'target_audience.*' => [Rule::in(array_keys(Audiens::PILIHAN))],
            'is_pinned' => ['boolean'],
            'publish_at' => ['nullable', 'date'],
            'expire_at' => ['nullable', 'date'],
        ], [
            'target_audience.required' => 'Pilih minimal satu audiens.',
            'target_audience.min' => 'Pilih minimal satu audiens.',
        ]);
    }
}
