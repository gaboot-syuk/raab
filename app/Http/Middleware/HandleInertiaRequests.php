<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * Blade root view untuk halaman Inertia (panel admin & dashboard anggota).
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dibagikan ke setiap halaman Inertia.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'roles' => $user ? $user->getRoleNames() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            ],
            'locale' => app()->getLocale(),
            'bahasa' => LaravelLocalization::getSupportedLocales(),
            // Dipakai tombol "Keluar". Keluar HARUS berupa form POST biasa, bukan
            // router.post(): tujuan sesudahnya adalah halaman Blade (/login)
            // yang bukan halaman Inertia, dan Inertia menampilkan modal galat
            // untuk setiap respons tanpa header X-Inertia.
            'csrf_token' => csrf_token(),
            'flash' => [
                'sukses' => fn () => $request->session()->get('sukses'),
                'galat' => fn () => $request->session()->get('galat'),
            ],
            // Ringkasan kecil untuk sidebar panel (mis. lencana pesan baru).
            'panel' => [
                'pesan_baru' => function () use ($user) {
                    if (! $user?->can('messages.view')) {
                        return 0;
                    }

                    return \App\Models\ContactMessage::query()->belumDibaca()->count();
                },
                /*
                 * Lencana notifikasi dihitung untuk SETIAP halaman, jadi
                 * jangan menambahkan kueri berat di sini. Satu hitungan ringan
                 * sudah cukup; daftar lengkapnya hanya dimuat di halaman
                 * notifikasi sendiri.
                 */
                'notifikasi_belum_dibaca' => fn () => $user ? $user->unreadNotifications()->count() : 0,
            ],
        ]);
    }
}
