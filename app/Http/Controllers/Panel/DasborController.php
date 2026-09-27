<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Dasbor panel pengurus.
 *
 * Angka yang tampil disesuaikan dengan izin pengguna: pengurus yang tidak
 * berhak atas suatu modul tidak melihat kartunya sama sekali, bukan melihat
 * angka nol yang menyesatkan.
 *
 * HALAMAN INI JUGA MENJADI PENENTU ARAH SETELAH MASUK.
 *
 * Fortify mengarahkan SEMUA pengguna ke `/panel` setelah masuk atau setelah
 * memverifikasi email. Kader dan alumni bukan pengurus, jadi halaman ini
 * melepas mereka ke area yang memang miliknya — dasbor anggota.
 *
 * Sebelum ini, kader yang baru mendaftar mendarat di panel pengurus dan
 * melihat halaman kosong bertuliskan "Belum ada menu yang tersedia untuk
 * peranmu. Hubungi Superadmin." Kesan yang tertinggal: aplikasinya rusak.
 * Padahal halaman yang seharusnya ia lihat — status pengajuannya — sudah ada,
 * hanya tidak pernah dituju.
 */
class DasborController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $pengguna = $request->user();

        if (! $pengguna?->pengurus()) {
            return redirect()
                ->route('anggota.dasbor')
                ->with('sukses', 'Kamu masuk ke area anggota. Panel pengurus hanya untuk pengurus rayon.');
        }

        return Inertia::render('Panel/Dasbor', [
            'ringkasan' => $this->ringkasan($pengguna),
            'pintasan' => $this->pintasan($pengguna),
            'peran' => $pengguna?->getRoleNames() ?? [],
            'izin' => $pengguna?->getAllPermissions()->pluck('name') ?? [],
            'aktivitas' => $this->aktivitas($pengguna),
        ]);
    }

    /**
     * Kartu angka ringkas — hanya modul yang boleh dilihat pengguna.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ringkasan(?User $pengguna): array
    {
        if (! $pengguna) {
            return [];
        }

        $kandidat = [
            [
                'izin' => 'messages.view',
                'label' => 'Pesan Belum Dibaca',
                'nilai' => fn (): int => ContactMessage::query()->belumDibaca()->count(),
                'tautan' => '/panel/pesan',
            ],
            [
                'izin' => 'users.view',
                'label' => 'Akun Pengurus',
                'nilai' => fn (): int => User::query()->count(),
                'tautan' => '/panel/pengguna',
            ],
            [
                'izin' => 'pages.manage',
                'label' => 'Halaman Statis',
                'nilai' => fn (): int => Page::query()->count(),
                'tautan' => '/panel/halaman',
            ],
            [
                'izin' => 'sliders.manage',
                'label' => 'Slider Beranda',
                'nilai' => fn (): int => Slider::query()->count(),
                'tautan' => '/panel/slider',
            ],
            [
                'izin' => 'media.view',
                'label' => 'Berkas Media',
                'nilai' => fn (): int => Media::query()->count(),
                'tautan' => '/panel/media',
            ],
        ];

        $hasil = [];

        foreach ($kandidat as $kartu) {
            if (! $pengguna->can($kartu['izin'])) {
                continue;
            }

            $hasil[] = [
                'label' => $kartu['label'],
                'nilai' => ($kartu['nilai'])(),
                'tautan' => $kartu['tautan'],
            ];
        }

        return $hasil;
    }

    /**
     * Tautan cepat ke pekerjaan yang paling sering dilakukan.
     *
     * @return array<int, array<string, string>>
     */
    private function pintasan(?User $pengguna): array
    {
        if (! $pengguna) {
            return [];
        }

        $semua = [
            'messages.view' => ['label' => 'Baca Pesan Masuk', 'tautan' => '/panel/pesan'],
            'pages.manage' => ['label' => 'Sunting Halaman Statis', 'tautan' => '/panel/halaman'],
            'sliders.manage' => ['label' => 'Atur Slider Beranda', 'tautan' => '/panel/slider'],
            'media.upload' => ['label' => 'Unggah Berkas', 'tautan' => '/panel/media'],
            'settings.site-manage' => ['label' => 'Pengaturan Situs', 'tautan' => '/panel/pengaturan'],
            'users.create' => ['label' => 'Tambah Akun Pengurus', 'tautan' => '/panel/pengguna'],
            'roles.manage' => ['label' => 'Atur Peran & Izin', 'tautan' => '/panel/peran'],
        ];

        $hasil = [];

        foreach ($semua as $izin => $pintasan) {
            if ($pengguna->can($izin)) {
                $hasil[] = $pintasan;
            }
        }

        return $hasil;
    }

    /**
     * Jejak perubahan terakhir (activity log).
     *
     * @return array<int, array<string, mixed>>
     */
    private function aktivitas(?User $pengguna): array
    {
        // Hanya Superadmin yang perlu melihat jejak audit seluruh organisasi.
        if (! $pengguna?->hasRole('superadmin')) {
            return [];
        }

        return Activity::query()
            ->with('causer:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $log): array => [
                'oleh' => $log->causer?->name ?? 'Sistem',
                'keterangan' => $log->description,
                'waktu' => $log->created_at?->translatedFormat('d M Y H:i'),
            ])
            ->all();
    }
}
