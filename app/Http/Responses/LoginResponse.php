<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ke mana pengguna diarahkan setelah berhasil masuk.
 *
 * Aturannya sederhana: pemegang peran kepengurusan masuk ke panel, sedangkan
 * kader/alumni (yang tidak punya peran) diarahkan ke dasbor anggota. Tanpa ini,
 * seorang kader akan mendarat di /panel dan langsung mendapat 403.
 *
 * URL yang semula dituju tetap dihormati (mis. pengguna yang terlempar ke
 * halaman masuk saat membuka tautan dalam).
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $pengguna = $request->user();

        $tujuan = ($pengguna && $pengguna->getRoleNames()->isNotEmpty())
            ? route('panel', absolute: false)
            : route('anggota.dasbor', absolute: false);

        return redirect()->intended($tujuan);
    }
}
