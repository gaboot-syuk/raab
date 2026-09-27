<?php

namespace App\Actions\Fortify;

use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Pendaftaran akun baru dari formulir /daftar.
 *
 * Pendaftaran punya DUA JALUR:
 *  - kader  → data kemahasiswaan wajib (NIM, fakultas, prodi, angkatan)
 *  - alumni → data alumni wajib (tahun lulus, instansi, domisili)
 *
 * Yang dibuat di sini bukan anggota resmi, melainkan:
 *  1. akun User (email belum terverifikasi — tautan dikirim Fortify), dan
 *  2. MemberApplication berstatus "menunggu" sebagai antrean verifikasi Sekretaris.
 *
 * Status keanggotaan baru menjadi "aktif"/"alumni" setelah Sekretaris menyetujui
 * (lihat App\Services\Keanggotaan::setujui).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $data = $this->validasi($input);

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Snapshot isian formulir — dipisah dari data akun agar catatan
            // pengajuan tetap utuh meski data profil berubah kemudian.
            $isian = collect($data)
                ->except(['password', 'password_confirmation', 'setuju'])
                ->all();

            MemberApplication::query()->create([
                'user_id' => $user->id,
                'jalur' => $data['jalur'],
                'status' => MemberApplication::STATUS_MENUNGGU,
                'data' => $isian,
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validasi(array $input): array
    {
        $kader = ($input['jalur'] ?? null) === Member::JALUR_KADER;
        $alumni = ($input['jalur'] ?? null) === Member::JALUR_ALUMNI;

        return Validator::make($input, [
            // --- Akun ---
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'string', 'email:filter', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'confirmed'],
            'jalur' => ['required', Rule::in([Member::JALUR_KADER, Member::JALUR_ALUMNI])],
            'setuju' => ['accepted'],

            // --- Data pribadi (kedua jalur) ---
            'jenis_kelamin' => ['required', Rule::in(['laki_laki', 'perempuan'])],
            'tempat_lahir' => ['required', 'string', 'max:120'],
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1970-01-01'],
            'telepon' => ['required', 'string', 'min:9', 'max:40'],
            'alamat' => ['required', 'string', 'min:10', 'max:500'],

            // --- Data kemahasiswaan (wajib kader, opsional alumni) ---
            'nim' => [$kader ? 'required' : 'nullable', 'string', 'max:40'],
            'fakultas' => [$kader ? 'required' : 'nullable', 'string', 'max:160'],
            'program_studi' => [$kader ? 'required' : 'nullable', 'string', 'max:160'],
            'angkatan' => [
                $kader ? 'required' : 'nullable',
                'integer',
                'min:2000',
                'max:'.(int) now()->format('Y'),
            ],

            // --- Data alumni (wajib untuk jalur alumni) ---
            'tahun_lulus' => [$alumni ? 'required' : 'nullable', 'integer', 'min:2000', 'max:'.(int) now()->format('Y')],
            'instansi' => [$alumni ? 'required' : 'nullable', 'string', 'max:190'],
            'jabatan' => ['nullable', 'string', 'max:160'],
            'bidang' => ['nullable', 'string', 'max:160'],
            'kota_domisili' => [$alumni ? 'required' : 'nullable', 'string', 'max:120'],

            // --- Minat (opsional) ---
            'unit_id' => ['nullable', 'integer', 'exists:organisation_units,id'],
            'keahlian' => ['nullable', 'string', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'setuju.accepted' => 'Mohon setujui pernyataan sebelum mendaftar.',
            'angkatan.min' => 'Tahun angkatan tidak wajar.',
        ], [
            'name' => 'nama lengkap',
            'jalur' => 'jalur pendaftaran',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'nim' => 'NIM',
            'program_studi' => 'program studi',
            'tahun_lulus' => 'tahun lulus',
            'kota_domisili' => 'kota domisili',
        ])->validate();
    }
}
