<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventField;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAnswer;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\User;
use App\Notifications\Event\KabarPendaftaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bisnis pendaftaran event.
 *
 * Dikumpulkan di satu kelas supaya janji-janji berikut tidak pernah bocor:
 *  1. Pendaftaran TERTUTUP OTOMATIS saat kuota penuh atau tanggal terlewat.
 *  2. Pendaftar ganda dengan email sama DITOLAK — termasuk bila ejaan namanya
 *     berbeda, lewat sidik jari data.
 *  3. Peserta yang lolos dapat dijadikan Kader Aktif TANPA input ulang data.
 */
class Pendaftaran
{
    /**
     * Simpan pendaftaran baru.
     *
     * @param  array<string, mixed>  $data     isian formulir bawaan
     * @param  array<int|string, mixed>  $jawaban  isian kolom tambahan
     *
     * @throws ValidationException
     */
    public function simpan(Event $event, array $data, array $jawaban, ?Request $request = null): EventRegistration
    {
        // Diperiksa ulang di sini — bukan hanya di tampilan — karena kuota bisa
        // penuh tepat di antara saat formulir dibuka dan saat dikirim.
        if ($alasan = $event->alasanTutup()) {
            throw ValidationException::withMessages(['pendaftaran' => $alasan]);
        }

        $email = Str::lower(trim((string) $data['email']));
        $sidik = $this->sidik($data);

        return DB::transaction(function () use ($event, $data, $jawaban, $request, $email, $sidik): EventRegistration {
            if ($event->pendaftaran()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email ini sudah terdaftar pada kegiatan ini. Cek kotak masuk untuk kode pendaftaranmu.',
                ]);
            }

            if ($event->pendaftaran()->where('sidik_data', $sidik)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Kami menemukan pendaftaran dengan data yang sama (nama, email, atau telepon). Pendaftaran ganda tidak diperlukan.',
                ]);
            }

            $pendaftaran = new EventRegistration;
            $pendaftaran->fill([...$data, 'email' => $email]);
            $pendaftaran->event_id = $event->id;
            $pendaftaran->kode_pendaftaran = $this->kodePendaftaran($event);
            $pendaftaran->status = EventRegistration::STATUS_MENUNGGU;
            $pendaftaran->sidik_data = $sidik;
            $pendaftaran->ip = $request?->ip();
            $pendaftaran->user_agent = Str::limit((string) $request?->userAgent(), 250, '');
            $pendaftaran->save();

            $this->simpanJawaban($pendaftaran, $jawaban);

            return $pendaftaran;
        }, 3);
    }

    /**
     * Simpan jawaban kolom tambahan.
     *
     * @param  array<int|string, mixed>  $jawaban
     */
    public function simpanJawaban(EventRegistration $pendaftaran, array $jawaban): void
    {
        if ($jawaban === []) {
            return;
        }

        $kolom = EventField::query()
            ->where('event_id', $pendaftaran->event_id)
            ->whereIn('id', array_map('intval', array_keys($jawaban)))
            ->get()
            ->keyBy('id');

        foreach ($jawaban as $fieldId => $nilai) {
            $field = $kolom->get((int) $fieldId);

            if (! $field) {
                continue;
            }

            $isi = match ($field->tipe) {
                EventField::TIPE_CENTANG => $nilai ? '1' : '0',
                default => is_array($nilai) ? implode(', ', $nilai) : (string) $nilai,
            };

            EventRegistrationAnswer::query()->updateOrCreate(
                ['event_registration_id' => $pendaftaran->id, 'event_field_id' => $field->id],
                ['nilai' => $isi],
            );
        }
    }

    /**
     * Kode pendaftaran yang enak dibacakan lewat telepon.
     *
     * Bentuk: MAPABA-2026-0007 (jenis, tahun, urutan 4 digit).
     *
     * Awalannya diambil dari JENIS, bukan dari slug: slug biasanya sudah memuat
     * tahun ("mapaba-2026") sehingga menempelkannya pada tahun lagi menghasilkan
     * kode seperti "MAPABA2026-2026-0001".
     */
    public function kodePendaftaran(Event $event): string
    {
        $awalan = Str::upper((string) Str::of($event->jenis)
            ->replaceMatches('/[^A-Za-z0-9]/', '')
            ->toString()) ?: 'EVENT';

        $tahun = ($event->mulai ?? now())->format('Y');

        $urutan = $event->pendaftaran()->count() + 1;

        do {
            $kode = $awalan.'-'.$tahun.'-'.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (EventRegistration::query()->where('kode_pendaftaran', $kode)->exists());

        return $kode;
    }

    /**
     * Sidik jari data pendaftar untuk mendeteksi pendaftaran ganda.
     *
     * Nama, email, dan telepon dinormalkan lebih dulu — "Ahmad Fauzi" dan
     * "ahmad  fauzi" harus dianggap orang yang sama.
     *
     * @param  array<string, mixed>  $data
     */
    public function sidik(array $data): string
    {
        $normalkan = fn (?string $nilai): string => Str::of((string) $nilai)
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->toString();

        return hash('sha256', implode('|', [
            $normalkan($data['nama_lengkap'] ?? ''),
            $normalkan($data['email'] ?? ''),
            $normalkan($data['telepon'] ?? ''),
        ]));
    }

    /* ------------------------------------------------------------------ */
    /* Pengelolaan oleh panitia                                            */
    /* ------------------------------------------------------------------ */

    public function verifikasi(EventRegistration $pendaftaran, User $pengurus, ?string $catatan = null): void
    {
        $pendaftaran->forceFill([
            'status' => EventRegistration::STATUS_TERVERIFIKASI,
            'catatan_panitia' => $catatan ?? $pendaftaran->catatan_panitia,
            'diverifikasi_oleh' => $pengurus->id,
            'diverifikasi_pada' => now(),
        ])->save();

        $this->kabari($pendaftaran, KabarPendaftaran::DIVERIFIKASI);
    }

    public function tolak(EventRegistration $pendaftaran, User $pengurus, string $catatan): void
    {
        $pendaftaran->forceFill([
            'status' => EventRegistration::STATUS_DITOLAK,
            'catatan_panitia' => $catatan,
            'diverifikasi_oleh' => $pengurus->id,
            'diverifikasi_pada' => now(),
        ])->save();

        $this->kabari($pendaftaran, KabarPendaftaran::DITOLAK, $catatan);
    }

    /**
     * Catat kehadiran di hari-H.
     *
     * Peserta yang ditandai hadir otomatis berstatus hadir; kalau sebelumnya
     * masih menunggu, ia sekaligus dianggap terverifikasi — karena kehadiran
     * adalah bukti terkuat bahwa yang bersangkutan benar-benar datang.
     */
    public function tandaiHadir(EventRegistration $pendaftaran, bool $hadir = true, ?User $pengurus = null): void
    {
        $pendaftaran->hadir = $hadir;

        if ($hadir) {
            $pendaftaran->status = EventRegistration::STATUS_HADIR;
            $pendaftaran->diverifikasi_oleh ??= $pengurus?->id;
            $pendaftaran->diverifikasi_pada ??= now();
        } elseif ($pendaftaran->status === EventRegistration::STATUS_HADIR) {
            $pendaftaran->status = EventRegistration::STATUS_TERVERIFIKASI;
        }

        $pendaftaran->save();
    }

    public function batalkan(EventRegistration $pendaftaran, ?string $alasan = null): void
    {
        $pendaftaran->forceFill([
            'status' => EventRegistration::STATUS_BATAL,
            'hadir' => false,
            'catatan_panitia' => $alasan ?? $pendaftaran->catatan_panitia,
        ])->save();

        $this->kabari($pendaftaran, KabarPendaftaran::DIBATALKAN, $alasan);
    }

    /* ------------------------------------------------------------------ */
    /* Promosi menjadi Kader Aktif                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Jadikan peserta sebagai Kader Aktif — TANPA meminta input ulang data.
     *
     * Langkahnya sengaja memakai jalur yang sudah ada (User → MemberApplication
     * → Keanggotaan::setujui) supaya peserta event mendapat perlakuan yang sama
     * dengan pendaftar biasa: nomor anggota diterbitkan, kartu dibuat, riwayat
     * dicatat, dan kabar dikirim.
     */
    public function promosikan(EventRegistration $pendaftaran, User $pengurus): Member
    {
        if ($pendaftaran->member_id !== null) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Peserta ini sudah dipromosikan menjadi anggota.',
            ]);
        }

        if (! $pendaftaran->sudahLolos()) {
            throw ValidationException::withMessages([
                'pendaftaran' => 'Hanya peserta yang sudah diverifikasi atau hadir yang dapat dijadikan Kader Aktif.',
            ]);
        }

        return DB::transaction(function () use ($pendaftaran, $pengurus): Member {
            $user = User::query()->firstOrCreate(
                ['email' => $pendaftaran->email],
                [
                    'name' => $pendaftaran->nama_lengkap,
                    // Kata sandi acak: akun ini belum dapat dipakai sampai
                    // pemiliknya menentukan sendiri lewat tautan undangan.
                    'password' => Str::password(32),
                ],
            );

            $pengajuan = MemberApplication::query()->create([
                'user_id' => $user->id,
                'jalur' => Member::JALUR_KADER,
                'status' => MemberApplication::STATUS_MENUNGGU,
                'data' => $pendaftaran->dataPendaftar(),
                'catatan_pengurus' => 'Dipromosikan dari peserta '.$pendaftaran->event?->labelJenis()
                    .' (kode '.$pendaftaran->kode_pendaftaran.').',
            ]);

            $member = app(Keanggotaan::class)->setujui($pengajuan, $pengurus);

            $pendaftaran->forceFill([
                'member_id' => $member->id,
                'dipromosikan_pada' => now(),
            ])->save();

            // Undangan menentukan kata sandi sendiri. Dikirim hanya bila akunnya
            // benar-benar belum pernah punya kata sandi yang dipakai.
            Password::broker()->sendResetLink(['email' => $user->email]);

            activity()
                ->performedOn($pendaftaran)
                ->withProperties(['member_id' => $member->id, 'nomor_anggota' => $member->nomor_anggota])
                ->log('Peserta event dipromosikan menjadi Kader Aktif');

            return $member;
        });
    }

    /**
     * Kirim kabar ke pendaftar (tanpa akun) lewat email yang ia isikan.
     */
    public function kabari(EventRegistration $pendaftaran, string $keadaan, ?string $catatan = null): void
    {
        Notification::route('mail', $pendaftaran->email)
            ->notify(new KabarPendaftaran($pendaftaran->fresh(), $keadaan, $catatan));
    }
}
