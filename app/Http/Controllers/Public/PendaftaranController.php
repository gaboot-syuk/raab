<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventField;
use App\Models\EventRegistration;
use App\Services\Captcha;
use App\Services\Pendaftaran;
use App\Support\Pengaturan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pendaftaran event Mapaba & PKD — TANPA akun.
 *
 * Alamat yang paling sering dibagikan lewat poster adalah yang paling pendek,
 * jadi `/pendaftaran/mapaba` dan `/pendaftaran/pkd` selalu mengarah ke event
 * yang sedang dibuka untuk jenis itu. Halaman ini juga menyediakan pemeriksaan
 * status berdasarkan kode, karena pendaftar tidak punya tempat untuk masuk.
 */
class PendaftaranController extends Controller
{
    public function __construct(
        private readonly Pendaftaran $pendaftaran,
    ) {}

    /**
     * Daftar seluruh kegiatan: yang sedang dibuka, akan datang, dan arsip.
     */
    public function index(): View
    {
        return view('public.pendaftaran-daftar', [
            'situs' => Pengaturan::semua(),
            'dibuka' => Event::query()->aktif()->terurut()->get()
                ->filter(fn (Event $event): bool => $event->menerimaPendaftaran())->values(),
            'mendatang' => Event::query()->aktif()->mendatang()->get()
                ->filter(fn (Event $event): bool => filled($event->alasanTutup()))->values(),
            'arsip' => Event::query()->aktif()->lampau()->get(),
        ]);
    }

    /**
     * Alamat pendek per jenis: /pendaftaran/mapaba dan /pendaftaran/pkd.
     *
     * Bila ada lebih dari satu event berjenis sama, yang dipakai adalah yang
     * sedang menerima pendaftaran; kalau tidak ada, yang paling baru.
     */
    public function jenis(string $jenis): View|RedirectResponse
    {
        abort_unless(array_key_exists($jenis, Event::JENIS), 404);

        $semua = Event::query()->aktif()->jenis($jenis)->terurut()->get();

        $event = $semua->first(fn (Event $item): bool => $item->menerimaPendaftaran()) ?? $semua->first();

        if (! $event) {
            return view('public.pendaftaran-kosong', [
                'situs' => Pengaturan::semua(),
                'jenis' => $jenis,
                'label' => Event::JENIS[$jenis],
            ]);
        }

        return $this->tampilkan($event);
    }

    public function detail(string $slug): View
    {
        $event = Event::query()->aktif()->slug($slug)->firstOrFail();

        return $this->tampilkan($event);
    }

    /**
     * Simpan pendaftaran.
     *
     * Dilindungi dua lapis: honeypot (kolom tersembunyi yang hanya diisi bot)
     * dan pembatasan 5 kiriman per menit per alamat IP.
     */
    public function kirim(Request $request, Event $event): RedirectResponse
    {
        // Honeypot: bot mengisi setiap kolom yang ditemuinya. Wajib bernilai
        // kosong — periksa sebagai boolean, bukan `filled()`.
        if (filled($request->input('tautan_web'))) {
            return redirect()->route('public.pendaftaran.detail', $event->getTranslation('slug', 'id'))
                ->with('galat', 'Pendaftaran tidak dapat diproses. Silakan hubungi panitia.');
        }

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:filter', 'max:190'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'jenis_kelamin' => ['nullable', 'in:laki_laki,perempuan'],
            'tempat_lahir' => ['nullable', 'string', 'max:120'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'nim' => ['nullable', 'string', 'max:40'],
            'fakultas' => ['nullable', 'string', 'max:160'],
            'program_studi' => ['nullable', 'string', 'max:160'],
            'angkatan' => ['nullable', 'integer', 'min:1990', 'max:'.(int) now()->addYear()->format('Y')],
            'instansi' => ['nullable', 'string', 'max:190'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'catatan_peserta' => ['nullable', 'string', 'max:1000'],
            'setuju' => ['accepted'],
            'jawaban' => ['nullable', 'array'],
            // Captcha: tidak apa-apa bila penyedianya belum dinyalakan.
            Captcha::KOLOM => Captcha::aturan(),
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi — kode pendaftaran dikirim ke sana.',
            'setuju.accepted' => 'Kamu perlu menyetujui ketentuan pendaftaran sebelum mengirim.',
        ]);

        $jawaban = $this->periksaKolom($request, $event);

        $pendaftaran = $this->pendaftaran->simpan(
            $event,
            collect($data)->except(['setuju', 'jawaban', 'tautan_web'])->all(),
            $jawaban,
            $request,
        );

        $this->pendaftaran->kabari($pendaftaran, \App\Notifications\Event\KabarPendaftaran::DITERIMA);

        return redirect()
            ->route('public.pendaftaran.sukses', $pendaftaran->kode_pendaftaran)
            ->with('sukses', 'Pendaftaranmu sudah kami terima.');
    }

    /**
     * Halaman konfirmasi berisi kode pendaftaran.
     */
    public function sukses(string $kode): View
    {
        $pendaftaran = EventRegistration::query()
            ->where('kode_pendaftaran', $kode)
            ->with('event')
            ->firstOrFail();

        return view('public.pendaftaran-sukses', [
            'situs' => Pengaturan::semua(),
            'pendaftaran' => $pendaftaran,
        ]);
    }

    /**
     * Pemeriksaan status mandiri lewat kode pendaftaran.
     *
     * WAJIB kode + email yang SAMA. Kode saja tidak cukup: kodenya berurutan
     * (MAPA-2026-0001, 0002, …) sehingga siapa pun dapat menebaknya dan
     * memanen nama, instansi, dan status pendaftar lain.
     */
    public function status(Request $request): View
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email:filter', 'max:190'],
        ], [], [
            'kode' => 'kode pendaftaran',
            'email' => 'email',
        ]);

        $pendaftaran = EventRegistration::query()
            ->where('kode_pendaftaran', trim($data['kode']))
            ->where('email', Str::lower(trim($data['email'])))
            ->with('event')
            ->first();

        return view('public.pendaftaran-status', [
            'situs' => Pengaturan::semua(),
            'kode' => trim($data['kode']),
            'email' => trim($data['email']),
            'pendaftaran' => $pendaftaran,
            'dicari' => true,
        ]);
    }

    /**
     * Kartu peserta versi publik — tetap memerlukan kode + email.
     */
    public function kartu(Request $request): View|RedirectResponse
    {
        $kode = trim($request->string('kode')->toString());
        $email = Str::lower(trim($request->string('email')->toString()));

        $pendaftaran = EventRegistration::query()
            ->where('kode_pendaftaran', $kode)
            ->where('email', $email)
            ->with(['event', 'jawaban.kolom'])
            ->first();

        // Kartu hanya untuk peserta yang sudah diverifikasi atau hadir.
        if (! $pendaftaran || ! $pendaftaran->sudahLolos()) {
            return redirect()
                ->route('public.pendaftaran.status')
                ->with('galat', 'Kartu hanya tersedia untuk pendaftaran yang sudah diverifikasi.');
        }

        return view('public.kartu-peserta', [
            'situs' => Pengaturan::semua(),
            'peserta' => $pendaftaran,
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function tampilkan(Event $event): View
    {
        return view('public.pendaftaran-detail', [
            'situs' => Pengaturan::semua(),
            'event' => $event,
            'kolom' => $event->kolom()->where('aktif', true)->get(),
            'keadaan' => $event->keadaanPendaftaran(),
            'kegiatanLain' => Event::query()->aktif()->whereKeyNot($event->id)->terurut()->limit(3)->get(),
        ]);
    }

    /**
     * Validasi kolom isian tambahan mengikuti tipe masing-masing.
     *
     * Dibuat di sini (bukan di EventController) karena aturannya menyangkut
     * kiriman pengunjung, bukan penyuntingan oleh panitia.
     *
     * @return array<int, mixed>
     */
    private function periksaKolom(Request $request, Event $event): array
    {
        $kolom = EventField::query()
            ->where('event_id', $event->id)
            ->where('aktif', true)
            ->get();

        if ($kolom->isEmpty()) {
            return [];
        }

        $aturan = [];
        $pesan = [];

        foreach ($kolom as $field) {
            $kunci = 'jawaban.'.$field->id;
            $label = $field->labelTeks();

            $aturan[$kunci] = match ($field->tipe) {
                EventField::TIPE_ANGKA => ['nullable', 'numeric'],
                EventField::TIPE_TANGGAL => ['nullable', 'date'],
                EventField::TIPE_CENTANG => ['nullable', 'boolean'],
                EventField::TIPE_PILIHAN => ['nullable', 'string', 'max:160'],
                EventField::TIPE_AREA => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:255'],
            };
            $pesan[$kunci.'.required'] = $label.' wajib diisi.';

            if ($field->wajib) {
                $aturan[$kunci][] = 'required';

                if ($field->tipe === EventField::TIPE_CENTANG) {
                    $aturan[$kunci][] = 'accepted';
                }
            }

            // Pilihan harus benar-benar salah satu yang disediakan panitia.
            if ($field->tipe === EventField::TIPE_PILIHAN) {
                $pilihan = $field->pilihanUntuk(app()->getLocale());
                $pilihan = $pilihan !== [] ? $pilihan : $field->pilihanUntuk('id');

                if ($pilihan !== []) {
                    $aturan[$kunci][] = 'in:'.implode(',', $pilihan);
                    $pesan[$kunci.'.in'] = $label.' harus salah satu dari: '.implode(', ', $pilihan).'.';
                }
            }
        }

        $request->validate($aturan, $pesan);

        return $request->input('jawaban') ?? [];
    }
}
