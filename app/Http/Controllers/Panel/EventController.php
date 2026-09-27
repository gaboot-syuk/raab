<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventField;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * CRUD event kaderisasi beserta kolom isian tambahannya.
 *
 * Menghapus event yang SUDAH punya pendaftar tidak diizinkan — data peserta
 * adalah arsip yang tidak boleh hilang karena salah klik. Nonaktifkan saja.
 */
class EventController extends Controller
{
    public function index(): Response
    {
        $daftar = Event::query()
            ->withCount('pendaftaran')
            ->terurut()
            ->get()
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'jenis' => $event->jenis,
                'label_jenis' => $event->labelJenis(),
                'judul' => $event->getTranslations('judul'),
                'slug' => $event->getTranslation('slug', 'id', false),
                'deskripsi' => $event->getTranslations('deskripsi'),
                'syarat' => $event->getTranslations('syarat'),
                'poster_media_id' => $event->poster_media_id,
                'poster' => $event->poster_media_id
                    ? Media::query()->find($event->poster_media_id)?->getUrl()
                    : null,
                'kuota' => $event->kuota,
                'biaya' => $event->biaya,
                'lokasi' => $event->lokasi,
                'mulai' => $event->mulai?->format('Y-m-d'),
                'selesai' => $event->selesai?->format('Y-m-d'),
                'pendaftaran_dibuka' => $event->pendaftaran_dibuka?->format('Y-m-d\TH:i'),
                'pendaftaran_ditutup' => $event->pendaftaran_ditutup?->format('Y-m-d\TH:i'),
                'aktif' => $event->aktif,
                'urutan' => $event->urutan,
                'jumlah_pendaftar' => $event->pendaftaran_count,
                'terisi' => $event->terisi(),
                'sisa' => $event->sisaKuota(),
                'keadaan' => $event->keadaanPendaftaran(),
                'kolom' => $event->kolom()
                    ->get()
                    ->map(fn (EventField $kolom): array => [
                        'id' => $kolom->id,
                        'kunci' => $kolom->kunci,
                        'label' => $kolom->getTranslations('label'),
                        'tipe' => $kolom->tipe,
                        'pilihan' => $kolom->pilihan,
                        'wajib' => $kolom->wajib,
                        'urutan' => $kolom->urutan,
                        'aktif' => $kolom->aktif,
                        'jumlah_jawaban' => $kolom->jawaban()->count(),
                    ]),
            ]);

        return Inertia::render('Panel/Event/Index', [
            'daftar' => $daftar,
            'jenis' => Event::JENIS,
            'tipeKolom' => EventField::TIPE,
            'pilihanPoster' => $this->pilihanPoster(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $event = new Event;
        $this->isi($event, $data);
        $event->slug = ['id' => Event::slugUnik($data['judul']['id'])];
        $event->save();

        return back()->with('sukses', 'Event '.$event->getTranslation('judul', 'id').' dibuat.');
    }

    public function perbarui(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validasi($request, $event);

        $this->isi($event, $data);

        // Slug lama dipertahankan agar tautan yang sudah disebar tidak mati.
        if (blank($event->getTranslation('slug', 'id', false))) {
            $event->setTranslations('slug', ['id' => Event::slugUnik($data['judul']['id'], $event->id)]);
        }

        $event->save();

        return back()->with('sukses', 'Event diperbarui.');
    }

    public function hapus(Event $event): RedirectResponse
    {
        if ($event->pendaftaran()->exists()) {
            return back()->with(
                'galat',
                'Event ini sudah memiliki '.$event->pendaftaran()->count().' pendaftar. Nonaktifkan saja — data peserta adalah arsip yang tidak boleh hilang.',
            );
        }

        $judul = (string) $event->getTranslation('judul', 'id', false);
        $event->delete();

        return back()->with('sukses', 'Event '.$judul.' dihapus.');
    }

    /* --------------------------- Kolom tambahan --------------------------- */

    public function simpanKolom(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'kunci' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::notIn(EventField::KUNCI_TERLARANG),
                Rule::unique('event_fields', 'kunci')->where(fn ($q) => $q->where('event_id', $event->id)),
            ],
            'label' => ['required', 'array'],
            'label.id' => ['required', 'string', 'max:160'],
            'label.en' => ['nullable', 'string', 'max:160'],
            'tipe' => ['required', Rule::in(array_keys(EventField::TIPE))],
            'pilihan' => ['nullable', 'array'],
            'pilihan.*' => ['nullable', 'string', 'max:120'],
            'pilihan.id' => ['nullable', 'array'],
            'pilihan.en' => ['nullable', 'array'],
            'wajib' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], [
            'kunci.regex' => 'Nama kolom hanya boleh huruf kecil, angka, dan garis bawah.',
            'kunci.not_in' => 'Nama kolom itu sudah dipakai formulir bawaan. Pilih nama lain.',
            'kunci.unique' => 'Kolom dengan nama itu sudah ada pada event ini.',
            'label.id.required' => 'Label kolom (Indonesia) wajib diisi.',
        ]);

        $kolom = new EventField;
        $kolom->event_id = $event->id;
        $kolom->kunci = $data['kunci'];
        $kolom->tipe = $data['tipe'];
        $kolom->wajib = (bool) ($data['wajib'] ?? false);
        $kolom->urutan = $data['urutan'] ?? 0;
        $kolom->aktif = (bool) ($data['aktif'] ?? true);
        $kolom->setTranslations('label', $this->bersihkan($data['label']));
        $kolom->pilihan = $this->rapikanPilihan($data['pilihan'] ?? []);
        $kolom->save();

        return back()->with('sukses', 'Kolom tambahan ditambahkan.');
    }

    public function perbaruiKolom(Request $request, EventField $kolom): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'array'],
            'label.id' => ['required', 'string', 'max:160'],
            'label.en' => ['nullable', 'string', 'max:160'],
            'tipe' => ['required', Rule::in(array_keys(EventField::TIPE))],
            'pilihan' => ['nullable', 'array'],
            'pilihan.*' => ['nullable', 'string', 'max:120'],
            'pilihan.id' => ['nullable', 'array'],
            'pilihan.en' => ['nullable', 'array'],
            'wajib' => ['boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], [
            'label.id.required' => 'Label kolom (Indonesia) wajib diisi.',
        ]);

        // `kunci` sengaja TIDAK boleh diubah: jawaban yang sudah tersimpan
        // menempel pada id kolom, tetapi mengubah namanya akan membingungkan
        // saat menelusuri data lama.
        $kolom->tipe = $data['tipe'];
        $kolom->wajib = (bool) ($data['wajib'] ?? false);
        $kolom->urutan = $data['urutan'] ?? 0;
        $kolom->aktif = (bool) ($data['aktif'] ?? true);
        $kolom->setTranslations('label', $this->bersihkan($data['label']));
        $kolom->pilihan = $this->rapikanPilihan($data['pilihan'] ?? []);
        $kolom->save();

        return back()->with('sukses', 'Kolom tambahan diperbarui.');
    }

    public function hapusKolom(EventField $kolom): RedirectResponse
    {
        $jawaban = $kolom->jawaban()->count();

        if ($jawaban > 0 && $kolom->event?->pendaftaran()->exists()) {
            return back()->with(
                'galat',
                'Kolom ini sudah memiliki '.$jawaban.' jawaban. Nonaktifkan saja agar jawaban lama tetap dapat dibaca.',
            );
        }

        $kolom->delete();

        return back()->with('sukses', 'Kolom tambahan dihapus.');
    }

    /* ------------------------------ Bantuan ------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Event $event = null): array
    {
        return $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Event::JENIS))],
            'judul' => ['required', 'array'],
            'judul.id' => ['required', 'string', 'max:160'],
            'judul.en' => ['nullable', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'array'],
            'deskripsi.id' => ['nullable', 'string', 'max:5000'],
            'deskripsi.en' => ['nullable', 'string', 'max:5000'],
            'syarat' => ['nullable', 'array'],
            'syarat.id' => ['nullable', 'string', 'max:3000'],
            'syarat.en' => ['nullable', 'string', 'max:3000'],
            'poster_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'kuota' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'biaya' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'pendaftaran_dibuka' => ['nullable', 'date'],
            'pendaftaran_ditutup' => ['nullable', 'date', 'after:pendaftaran_dibuka'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
            'lokasi' => ['nullable', 'string', 'max:190'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
        ], [
            'judul.id.required' => 'Judul event (Indonesia) wajib diisi.',
            'pendaftaran_ditutup.after' => 'Penutupan pendaftaran harus setelah pembukaannya.',
            'selesai.after_or_equal' => 'Tanggal selesai tidak boleh mendahului tanggal mulai.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isi(Event $event, array $data): void
    {
        $event->jenis = $data['jenis'];
        $event->poster_media_id = $data['poster_media_id'] ?? null;
        // Kosong berarti TIDAK TERBATAS — bukan nol.
        $event->kuota = filled($data['kuota'] ?? null) ? (int) $data['kuota'] : null;
        $event->biaya = (int) ($data['biaya'] ?? 0);
        $event->pendaftaran_dibuka = $data['pendaftaran_dibuka'] ?? null;
        $event->pendaftaran_ditutup = $data['pendaftaran_ditutup'] ?? null;
        $event->mulai = $data['mulai'] ?? null;
        $event->selesai = $data['selesai'] ?? null;
        $event->lokasi = $data['lokasi'] ?? null;
        $event->urutan = $data['urutan'] ?? 0;
        $event->aktif = (bool) ($data['aktif'] ?? true);

        $event->setTranslations('judul', $this->bersihkan($data['judul']));

        foreach (['deskripsi', 'syarat'] as $kolom) {
            $nilai = $this->bersihkan($data[$kolom] ?? []);
            $event->setTranslations($kolom, $nilai);
        }
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, string>
     */
    private function bersihkan(array $nilai): array
    {
        return array_filter($nilai, fn ($isi) => is_string($isi) && trim($isi) !== '');
    }

    /**
     * Rapikan daftar pilihan: boleh larik sederhana, boleh dipisah per bahasa.
     *
     * @param  array<string, mixed>  $pilihan
     * @return array<string, mixed>|null
     */
    private function rapikanPilihan(array $pilihan): ?array
    {
        $perBahasa = array_filter(
            $pilihan,
            fn ($nilai) => is_array($nilai) && array_filter($nilai, fn ($i) => is_string($i) && trim($i) !== ''),
        );

        if ($perBahasa !== []) {
            return array_map(
                fn (array $daftar): array => array_values(array_filter(array_map('trim', $daftar))),
                $perBahasa,
            );
        }

        $sederhana = array_values(array_filter(
            array_map(fn ($nilai) => is_string($nilai) ? trim($nilai) : '', $pilihan),
        ));

        return $sederhana === [] ? null : $sederhana;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function pilihanPoster()
    {
        return Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'nama' => $media->name ?: $media->file_name,
                'url' => $media->hasGeneratedConversion('kecil') ? $media->getUrl('kecil') : $media->getUrl(),
            ]);
    }
}
