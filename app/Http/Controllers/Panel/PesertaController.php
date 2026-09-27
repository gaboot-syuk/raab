<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventField;
use App\Models\EventRegistration;
use App\Services\Pendaftaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponsFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * Manajemen peserta event: daftar, saring, verifikasi, catat kehadiran,
 * ekspor, cetak kartu, dan promosi menjadi Kader Aktif.
 */
class PesertaController extends Controller
{
    public function __construct(
        private readonly Pendaftaran $pendaftaran,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $eventId = $request->integer('event');
        $event = $eventId > 0
            ? Event::query()->find($eventId)
            : Event::query()->terurut()->first();

        $saring = [
            'status' => $request->string('status')->toString(),
            'cari' => trim($request->string('cari')->toString()),
            'hadir' => $request->string('hadir')->toString(),
        ];

        $daftar = $event
            ? EventRegistration::query()
                ->where('event_id', $event->id)
                ->with(['jawaban.kolom:id,label,kunci', 'member:id,nomor_anggota'])
                ->when($saring['status'] !== '', fn ($q) => $q->where('status', $saring['status']))
                ->when($saring['hadir'] === '1', fn ($q) => $q->where('hadir', true))
                ->when($saring['hadir'] === '0', fn ($q) => $q->where('hadir', false))
                ->when($saring['cari'] !== '', fn ($q) => $q->where(
                    fn ($qq) => $qq
                        ->where('nama_lengkap', 'like', "%{$saring['cari']}%")
                        ->orWhere('email', 'like', "%{$saring['cari']}%")
                        ->orWhere('kode_pendaftaran', 'like', "%{$saring['cari']}%")
                        ->orWhere('telepon', 'like', "%{$saring['cari']}%"),
                ))
                // Urutan status memakai CASE, BUKAN FIELD(): FIELD() hanya ada di
                // MySQL sehingga halaman ini gagal di SQLite (dan akan gagal juga
                // di PostgreSQL). CASE berjalan di semua basis data yang didukung.
                ->orderByRaw("CASE status
                    WHEN 'menunggu' THEN 1
                    WHEN 'terverifikasi' THEN 2
                    WHEN 'hadir' THEN 3
                    WHEN 'ditolak' THEN 4
                    ELSE 5 END")
                ->orderBy('nama_lengkap')
                ->get()
                ->map(fn (EventRegistration $peserta): array => [
                    'id' => $peserta->id,
                    'kode_pendaftaran' => $peserta->kode_pendaftaran,
                    'nama_lengkap' => $peserta->nama_lengkap,
                    'email' => $peserta->email,
                    'telepon' => $peserta->telepon,
                    'jenis_kelamin' => $peserta->jenis_kelamin,
                    'nim' => $peserta->nim,
                    'fakultas' => $peserta->fakultas,
                    'program_studi' => $peserta->program_studi,
                    'angkatan' => $peserta->angkatan,
                    'instansi' => $peserta->instansi,
                    'alamat' => $peserta->alamat,
                    'tempat_lahir' => $peserta->tempat_lahir,
                    'tanggal_lahir' => $peserta->tanggal_lahir?->format('Y-m-d'),
                    'status' => $peserta->status,
                    'label_status' => $peserta->labelStatus(),
                    'hadir' => $peserta->hadir,
                    'catatan_peserta' => $peserta->catatan_peserta,
                    'catatan_panitia' => $peserta->catatan_panitia,
                    'dibuat_pada' => $peserta->created_at?->translatedFormat('d M Y, H:i'),
                    'member_id' => $peserta->member_id,
                    'nomor_anggota' => $peserta->member?->nomor_anggota,
                    'boleh_dipromosikan' => $peserta->sudahLolos() && ! $peserta->sudahDipromosikan(),
                    'jawaban' => $peserta->jawaban->map(fn ($jawaban): array => [
                        'label' => $jawaban->kolom?->labelTeks() ?? '(kolom dihapus)',
                        'nilai' => $jawaban->nilai,
                    ])->values(),
                ])
            : collect();

        return Inertia::render('Panel/Event/Peserta', [
            'event' => $event
                ? [
                    'id' => $event->id,
                    'judul' => $event->getTranslation('judul', 'id', false),
                    'label_jenis' => $event->labelJenis(),
                    'kuota' => $event->kuota,
                    'keadaan' => $event->keadaanPendaftaran(),
                ]
                : null,
            'daftarEvent' => Event::query()->terurut()->get()
                ->map(fn (Event $item): array => [
                    'id' => $item->id,
                    'label' => $item->labelJenis().' — '.$item->getTranslation('judul', 'id', false),
                ]),
            'peserta' => $daftar,
            'saring' => $saring,
            'status' => EventRegistration::STATUS,
            'ringkasan' => $event ? $this->ringkasan($event) : null,
        ]);
    }

    public function verifikasi(Request $request, EventRegistration $peserta): RedirectResponse
    {
        $data = $request->validate(['catatan_panitia' => ['nullable', 'string', 'max:1000']]);

        $this->pendaftaran->verifikasi($peserta, $request->user(), $data['catatan_panitia'] ?? null);

        return back()->with('sukses', $peserta->nama_lengkap.' diverifikasi. Kabar sudah dikirim ke emailnya.');
    }

    public function tolak(Request $request, EventRegistration $peserta): RedirectResponse
    {
        $data = $request->validate(
            ['catatan_panitia' => ['required', 'string', 'max:1000']],
            ['catatan_panitia.required' => 'Alasan penolakan wajib diisi agar pendaftar tahu sebabnya.'],
        );

        $this->pendaftaran->tolak($peserta, $request->user(), $data['catatan_panitia']);

        return back()->with('sukses', $peserta->nama_lengkap.' ditolak.');
    }

    public function hadir(Request $request, EventRegistration $peserta): RedirectResponse
    {
        $data = $request->validate(['hadir' => ['boolean']]);
        $hadir = (bool) ($data['hadir'] ?? true);

        $this->pendaftaran->tandaiHadir($peserta, $hadir, $request->user());

        return back()->with('sukses', $hadir
            ? $peserta->nama_lengkap.' ditandai hadir.'
            : 'Tanda hadir '.$peserta->nama_lengkap.' dibatalkan.');
    }

    public function catatan(Request $request, EventRegistration $peserta): RedirectResponse
    {
        $data = $request->validate(['catatan_panitia' => ['nullable', 'string', 'max:1000']]);

        $peserta->forceFill(['catatan_panitia' => $data['catatan_panitia'] ?? null])->save();

        return back()->with('sukses', 'Catatan panitia disimpan.');
    }

    public function batalkan(Request $request, EventRegistration $peserta): RedirectResponse
    {
        $data = $request->validate(['catatan_panitia' => ['nullable', 'string', 'max:1000']]);

        $this->pendaftaran->batalkan($peserta, $data['catatan_panitia'] ?? null);

        return back()->with('sukses', 'Pendaftaran '.$peserta->nama_lengkap.' dibatalkan. Kursinya kembali tersedia.');
    }

    /**
     * Promosi peserta menjadi Kader Aktif — data terisi dari formulir event,
     * tanpa perlu mengetik ulang apa pun.
     */
    public function promosikan(Request $request, EventRegistration $peserta): RedirectResponse
    {
        try {
            $member = $this->pendaftaran->promosikan($peserta, $request->user());
        } catch (ValidationException $e) {
            return back()->with('galat', collect($e->errors())->flatten()->first());
        }

        return back()->with(
            'sukses',
            $peserta->nama_lengkap.' kini Kader Aktif dengan nomor '.$member->nomor_anggota.'. Undangan penentuan kata sandi sudah dikirim ke '.$peserta->email.'.',
        );
    }

    /**
     * Ekspor peserta ke CSV.
     *
     * Format CSV dipilih (bukan xlsx) karena server ini tidak memiliki ekstensi
     * zip yang dibutuhkan pembuat xlsx. Berkas CSV dengan pemisah titik koma
     * dan penanda BOM UTF-8 langsung terbuka rapi di Excel berbahasa Indonesia.
     */
    public function ekspor(Request $request, Event $event): StreamedResponse
    {
        $saring = [
            'status' => $request->string('status')->toString(),
            'hadir' => $request->string('hadir')->toString(),
            'cari' => trim($request->string('cari')->toString()),
        ];

        $kolomTambahan = EventField::query()
            ->where('event_id', $event->id)
            ->orderBy('urutan')
            ->get();

        $peserta = EventRegistration::query()
            ->where('event_id', $event->id)
            ->with('jawaban')
            ->when($saring['status'] !== '', fn ($q) => $q->where('status', $saring['status']))
            ->when($saring['hadir'] === '1', fn ($q) => $q->where('hadir', true))
            ->when($saring['hadir'] === '0', fn ($q) => $q->where('hadir', false))
            ->when($saring['cari'] !== '', fn ($q) => $q->where(
                fn ($qq) => $qq
                    ->where('nama_lengkap', 'like', "%{$saring['cari']}%")
                    ->orWhere('email', 'like', "%{$saring['cari']}%")
                    ->orWhere('kode_pendaftaran', 'like', "%{$saring['cari']}%"),
            ))
            ->orderBy('nama_lengkap')
            ->get();

        $judul = (string) $event->getTranslation('judul', 'id', false);
        $namaBerkas = 'peserta-'.Str::slug($judul).'-'.now()->format('Ymd-His').'.csv';

        return ResponsFacade::streamDownload(function () use ($peserta, $kolomTambahan): void {
            $keluaran = fopen('php://output', 'wb');

            // BOM UTF-8: tanpa ini Excel merusak huruf beraksen.
            fwrite($keluaran, "\xEF\xBB\xBF");
            // Petunjuk pemisah agar Excel memecah kolom dengan benar.
            fwrite($keluaran, "sep=;\n");

            $kepala = [
                'Kode Pendaftaran', 'Nama Lengkap', 'Email', 'Telepon', 'Jenis Kelamin',
                'Tempat Lahir', 'Tanggal Lahir', 'NIM', 'Fakultas', 'Program Studi',
                'Angkatan', 'Instansi/Asal', 'Alamat', 'Status', 'Hadir',
                'Catatan Peserta', 'Catatan Panitia', 'Waktu Daftar',
            ];

            foreach ($kolomTambahan as $kolom) {
                $kepala[] = $kolom->labelTeks();
            }

            $this->tulisBaris($keluaran, $kepala);

            foreach ($peserta as $baris) {
                $jawaban = $baris->jawaban->keyBy('event_field_id');

                $nilai = [
                    $baris->kode_pendaftaran,
                    $baris->nama_lengkap,
                    $baris->email,
                    $baris->telepon,
                    $baris->jenis_kelamin,
                    $baris->tempat_lahir,
                    $baris->tanggal_lahir?->format('d/m/Y'),
                    " ".$baris->nim,          // spasi depan: jaga NIM panjang tetap teks
                    $baris->fakultas,
                    $baris->program_studi,
                    $baris->angkatan,
                    $baris->instansi,
                    $baris->alamat,
                    $baris->labelStatus(),
                    $baris->hadir ? 'Ya' : 'Tidak',
                    $baris->catatan_peserta,
                    $baris->catatan_panitia,
                    $baris->created_at?->format('d/m/Y H:i'),
                ];

                foreach ($kolomTambahan as $kolom) {
                    $nilai[] = $jawaban->get($kolom->id)?->nilai;
                }

                $this->tulisBaris($keluaran, $nilai);
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Kartu peserta siap cetak.
     *
     * Memakai halaman Blade dengan gaya cetak (bukan PDF) supaya panitia dapat
     * langsung menekan Ctrl+P di loket pendaftaran — tidak ada berkas yang perlu
     * diunduh lebih dulu.
     */
    public function kartu(EventRegistration $peserta): View
    {
        $peserta->load(['event', 'jawaban.kolom']);

        return view('public.kartu-peserta', [
            'situs' => \App\Support\Pengaturan::semua(),
            'peserta' => $peserta,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function ringkasan(Event $event): array
    {
        $semua = EventRegistration::query()->where('event_id', $event->id);

        return [
            'total' => (clone $semua)->count(),
            'menunggu' => (clone $semua)->where('status', EventRegistration::STATUS_MENUNGGU)->count(),
            'terverifikasi' => (clone $semua)->where('status', EventRegistration::STATUS_TERVERIFIKASI)->count(),
            'hadir' => (clone $semua)->where('status', EventRegistration::STATUS_HADIR)->count(),
            'ditolak' => (clone $semua)->where('status', EventRegistration::STATUS_DITOLAK)->count(),
            'batal' => (clone $semua)->where('status', EventRegistration::STATUS_BATAL)->count(),
            'siap_dipromosikan' => (clone $semua)->terverifikasi()->belumDipromosikan()->count(),
        ];
    }

    /**
     * @param  array<int, mixed>  $nilai
     */
    private function tulisBaris($keluaran, array $nilai): void
    {
        fputcsv($keluaran, array_map(
            static fn ($isi) => (string) ($isi ?? ''),
            $nilai,
        ), ';');
    }
}
