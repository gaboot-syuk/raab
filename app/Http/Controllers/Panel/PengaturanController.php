<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Penerjemah;
use Illuminate\Support\Str;
use App\Http\Requests\Panel\PengaturanRequest;
use App\Models\SiteSetting;
use App\Support\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan situs: identitas rayon, sekretariat, SEO, parameter perpustakaan,
 * dan tampilan. Nilainya dipakai langsung oleh layout publik (footer, kontak),
 * sehingga pengurus dapat mengganti placeholder tanpa menyentuh kode.
 */
class PengaturanController extends Controller
{
    /**
     * Label manusiawi untuk setiap kelompok pengaturan.
     */
    private const LABEL_GRUP = [
        'identitas' => 'Identitas Organisasi',
        'sekretariat' => 'Sekretariat & Kontak',
        'seo' => 'Mesin Pencari (SEO)',
        'perpustakaan' => 'Parameter Perpustakaan',
        'tampilan' => 'Tampilan',
        'statistik' => 'Statistik Beranda',
        'terjemahan' => 'Terjemahan Otomatis',
    ];

    public function index(): Response
    {
        $grup = SiteSetting::query()
            ->orderBy('id')
            ->get()
            ->groupBy('grup')
            ->map(fn ($baris, string $namaGrup): array => [
                'nama' => $namaGrup,
                'label' => self::LABEL_GRUP[$namaGrup] ?? ucfirst($namaGrup),
                'item' => $baris->map(fn (SiteSetting $setting): array => [
                    'kunci' => $setting->kunci,
                    'label' => $setting->label,
                    'tipe' => $setting->tipe,
                    'nilai_id' => $setting->getTranslation('nilai', 'id', false) ?? '',
                    'nilai_en' => $setting->getTranslation('nilai', 'en', false) ?? '',
                    // Pengaturan bertipe 'pilihan' dirender sebagai dropdown.
                    'pilihan' => filled($setting->pilihan)
                        ? collect($setting->pilihan)
                            ->mapWithKeys(fn (string $nilai): array => [
                                $nilai => Penerjemah::DRIVER[$nilai] ?? Str::headline($nilai),
                            ])
                            ->all()
                        : null,
                ])->values()->all(),
            ])
            ->values();

        return Inertia::render('Panel/Pengaturan/Index', [
            'grup' => $grup,
        ]);
    }

    public function perbarui(PengaturanRequest $request): RedirectResponse
    {
        $jumlah = 0;

        foreach ($request->validated('nilai') as $kunci => $nilai) {
            $setting = SiteSetting::query()->where('kunci', $kunci)->first();

            if ($setting === null) {
                continue;
            }

            $setting->setTranslation('nilai', 'id', (string) ($nilai['id'] ?? ''));
            $setting->setTranslation('nilai', 'en', (string) ($nilai['en'] ?? ''));
            $setting->save();

            $jumlah++;
        }

        Pengaturan::lupakan();

        return back()->with('sukses', "{$jumlah} pengaturan berhasil disimpan.");
    }
}
