<?php

namespace App\Rules;

use App\Services\Captcha as LayananCaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan validasi captcha.
 *
 * Dipisah dari layanannya supaya formulir cukup menulis
 * `Captcha::aturan()` — dan supaya pemeriksaannya bisa diuji tanpa harus
 * benar-benar mengirim permintaan ke layanan captcha.
 */
class Captcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hasil = LayananCaptcha::periksa(
            is_string($value) ? $value : null,
            request()->ip(),
        );

        if (! $hasil['berhasil']) {
            $fail($hasil['pesan']);
        }
    }
}
