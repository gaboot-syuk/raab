<?php

namespace App\Http\Controllers\Panel\Organisasi;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periode kepengurusan.
 *
 * MENGUBAH PERIODE AKTIF TIDAK MENGHAPUS APA PUN. Penugasan, jabatan, dan
 * seluruh riwayat tetap menempel pada periodenya masing-masing; yang berubah
 * hanya penanda `aktif` yang menentukan periode mana yang tampil secara bawaan
 * pada bagan struktur dan halaman publik.
 *
 * Karena itu modul ini hanya dipegang Superadmin (izin `periods.*`).
 */
class PeriodeController extends Controller
{
    public function index(): Response
    {
        $daftar = Period::query()
            ->withCount('penugasan')
            ->orderByDesc('tahun_selesai')
            ->orderByDesc('tahun_mulai')
            ->get()
            ->map(fn (Period $periode): array => [
                'id' => $periode->id,
                'nama' => $periode->nama,
                'tahun_mulai' => $periode->tahun_mulai,
                'tahun_selesai' => $periode->tahun_selesai,
                'mulai' => $periode->mulai?->format('Y-m-d'),
                'selesai' => $periode->selesai?->format('Y-m-d'),
                'aktif' => $periode->aktif,
                'urutan' => $periode->urutan,
                'jumlah_penugasan' => $periode->penugasan_count,
            ]);

        return Inertia::render('Panel/Organisasi/Periode', [
            'daftar' => $daftar,
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $periode = new Period;
        $periode->fill($data);
        $periode->nama = $periode->nama ?: $data['tahun_mulai'].'/'.$data['tahun_selesai'];
        $periode->save();

        $this->pastikanSatuAktif($periode);

        return back()->with('sukses', 'Periode '.$periode->nama.' ditambahkan.');
    }

    public function perbarui(Request $request, Period $periode): RedirectResponse
    {
        $data = $this->validasi($request, $periode);

        $periode->fill($data);
        $periode->save();

        $this->pastikanSatuAktif($periode);

        return back()->with('sukses', 'Periode '.$periode->nama.' diperbarui.');
    }

    /**
     * Tandai satu periode sebagai periode berjalan.
     */
    public function aktifkan(Period $periode): RedirectResponse
    {
        DB::transaction(function () use ($periode): void {
            Period::query()->whereKeyNot($periode->id)->update(['aktif' => false]);
            $periode->forceFill(['aktif' => true])->save();
        });

        return back()->with('sukses', 'Periode '.$periode->nama.' kini menjadi periode berjalan.');
    }

    public function hapus(Period $periode): RedirectResponse
    {
        if ($periode->penugasan()->exists()) {
            return back()->with(
                'galat',
                'Periode '.$periode->nama.' masih memiliki penugasan pengurus. Hapus penugasannya lebih dulu — riwayat kepengurusan tidak boleh hilang tanpa sengaja.',
            );
        }

        if ($periode->aktif) {
            return back()->with('galat', 'Periode yang sedang berjalan tidak dapat dihapus. Tandai periode lain sebagai periode berjalan lebih dulu.');
        }

        $nama = $periode->nama;
        $periode->delete();

        return back()->with('sukses', 'Periode '.$nama.' dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Period $periode = null): array
    {
        return $request->validate([
            'nama' => ['nullable', 'string', 'max:60'],
            'tahun_mulai' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_selesai' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:tahun_mulai'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], [], [
            'tahun_mulai' => 'tahun mulai',
            'tahun_selesai' => 'tahun selesai',
        ]);
    }

    /**
     * Bila periode ditandai aktif dari formulir, pastikan hanya satu yang aktif.
     */
    private function pastikanSatuAktif(Period $periode): void
    {
        if (! $periode->aktif) {
            return;
        }

        Period::query()->whereKeyNot($periode->id)->update(['aktif' => false]);
    }
}
