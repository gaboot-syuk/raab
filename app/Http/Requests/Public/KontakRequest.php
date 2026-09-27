<?php

namespace App\Http\Requests\Public;

use App\Services\Captcha;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi formulir "Kontak Rayon".
 *
 * Formulir ini terbuka untuk umum (tanpa akun), jadi selain validasi biasa
 * dipasang juga jebakan spam (honeypot), pembatasan laju di sisi rute, dan
 * captcha bila penyedianya memang dinyalakan (lihat App\Services\Captcha).
 */
class KontakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'email:filter', 'max:190'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'asal' => ['nullable', 'string', 'max:190'],
            'jenis' => ['required', 'in:umum,kerjasama,undangan,lainnya'],
            'subjek' => ['required', 'string', 'min:4', 'max:190'],
            'pesan' => ['required', 'string', 'min:20', 'max:5000'],
            'setuju' => ['accepted'],
            // Honeypot: kolom ini tersembunyi dari manusia, hanya bot yang mengisinya.
            'website' => ['nullable', 'prohibited'],
            // Captcha: tidak apa-apa bila penyedianya belum dinyalakan.
            Captcha::KOLOM => Captcha::aturan(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama' => 'nama lengkap',
            'email' => 'email',
            'telepon' => 'nomor telepon',
            'asal' => 'asal instansi',
            'jenis' => 'jenis pesan',
            'subjek' => 'subjek',
            'pesan' => 'isi pesan',
            'setuju' => 'persetujuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'Pesan tidak dapat dikirim.',
            'pesan.min' => 'Isi pesan minimal :min karakter agar kami dapat memahami maksudmu.',
            'setuju.accepted' => 'Mohon setujui pernyataan sebelum mengirim pesan.',
        ];
    }
}
