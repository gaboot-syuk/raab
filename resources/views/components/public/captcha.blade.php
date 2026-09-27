@php
    use App\Services\Captcha as LayananCaptcha;

    $penyedia = LayananCaptcha::penyedia();
    $kunciSitus = LayananCaptcha::kunciSitus();
@endphp

{{--
    Widget captcha untuk formulir publik.

    TIDAK merender apa pun bila captcha tidak aktif, sehingga formulir tidak
    pernah menampilkan kotak kosong atau pesan galat tentang captcha yang
    sebenarnya tidak dipakai. Berkas ini juga sengaja tidak memuat kunci
    rahasia: yang dirender ke peramban hanya kunci SITUS, yang memang untuk itu.
--}}
@if (LayananCaptcha::aktif())
    <div class="brutal-sm mt-4 bg-paper-alt p-3">
        @switch($penyedia)
            @case(LayananCaptcha::TURNSTILE)
                <div
                    class="cf-turnstile"
                    data-sitekey="{{ $kunciSitus }}"
                    data-response-field-name="{{ LayananCaptcha::KOLOM }}"
                ></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @break

            @case(LayananCaptcha::HCAPTCHA)
                <div
                    class="h-captcha"
                    data-sitekey="{{ $kunciSitus }}"
                    data-response-field-name="{{ LayananCaptcha::KOLOM }}"
                ></div>
                <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
            @break

            @case(LayananCaptcha::RECAPTCHA)
                <div
                    class="g-recaptcha"
                    data-sitekey="{{ $kunciSitus }}"
                    data-response-field-name="{{ LayananCaptcha::KOLOM }}"
                ></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            @break
        @endswitch

        <p class="mt-2 text-[11px] text-muted">
            Penyaringan ini memastikan kiriman datang dari manusia, bukan dari program otomatis.
        </p>

        {{--
            Kolom cadangan. Sebagian widget menuliskan jawabannya ke `g-recaptcha-response`
            atau `cf-turnstile-response`, bukan ke nama kolom yang kita minta. Kolom ini
            diisi oleh skrip kecil di bawah supaya nama kolomnya selalu sama.
        --}}
        <input type="hidden" name="{{ LayananCaptcha::KOLOM }}" value="{{ old(LayananCaptcha::KOLOM) }}">

        <script>
            (function () {
                var kolom = @json(LayananCaptcha::KOLOM);
                var form = document.currentScript.closest('form');

                if (! form) {
                    return;
                }

                form.addEventListener('submit', function () {
                    var pilihan = ['cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response'];

                    for (var i = 0; i < pilihan.length; i++) {
                        var nilai = form.querySelector('[name="' + pilihan[i] + '"]');

                        if (nilai && nilai.value) {
                            form.querySelector('[name="' + kolom + '"]').value = nilai.value;

                            return;
                        }
                    }
                });
            })();
        </script>

        @error(LayananCaptcha::KOLOM)
            <p class="mt-2 text-xs font-bold text-accent-600">{{ $message }}</p>
        @enderror
    </div>
@endif
