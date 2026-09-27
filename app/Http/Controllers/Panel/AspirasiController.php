<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\MenjalankanAksi;
use App\Http\Controllers\Controller;
use App\Models\Aspiration;
use App\Services\Aspirasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as ResponsHttp;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aspirasi dari sisi pengurus.
 *
 * INI SATU-SATUNYA TEMPAT IDENTITAS PENGIRIM DITAMPILKAN, dan itupun dibatasi
 * izin `aspirations.view-identity`. Izin itu dipisah dari `aspirations.view`
 * dengan sengaja: seorang pengurus boleh saja perlu membaca isi aspirasi tanpa
 * perlu tahu siapa yang mengirimnya.
 *
 * Kolom identitas hanya ikut di query bila penggunanya memang berhak — bukan
 * dikirim lalu disembunyikan di tampilan, karena data yang dikirim ke peramban
 * sudah bisa dibaca siapa pun yang membuka alat pengembang.
 */
class AspirasiController extends Controller
{
    use MenjalankanAksi;

    public function __construct(private Aspirasi $aspirasi) {}

    public function index(Request $request): Response
    {
        $pengguna = $request->user();
        $bolehLihatIdentitas = $pengguna->can('aspirations.view-identity');

        $status = $request->string('status')->toString();
        $kategori = $request->string('kategori')->toString();

        $daftar = $this->aspirasi->antrean($status ?: null, $kategori ?: null)
            ->map(fn (Aspiration $a): array => [
                'id' => $a->id,
                'nomor_tiket' => $a->nomor_tiket,
                'judul' => $a->judulTeks(),
                'isi' => $a->isi,
                'kategori' => $a->kategori,
                'label_kategori' => $a->labelKategori(),
                'status' => $a->status,
                'label_status' => $a->labelStatus(),
                'anonim' => $a->anonim,
                'tampil_publik' => $a->tampil_publik,
                'tanggapan' => $a->tanggapan,
                'penanggap' => $a->penanggap?->name,
                'ditanggapi_pada' => $a->ditanggapi_pada?->translatedFormat('d M Y, H:i'),
                'dibuat' => $a->created_at?->translatedFormat('d M Y, H:i'),
                // Identitas HANYA bila berhak.
                'nama_pengirim' => $bolehLihatIdentitas ? $a->nama_pengirim : null,
                'email_pengirim' => $bolehLihatIdentitas ? $a->email_pengirim : null,
                'telepon_pengirim' => $bolehLihatIdentitas ? $a->telepon_pengirim : null,
                'punya_akun' => $a->member_id !== null,
                // Ditandai bila badan suratnya memuat data pribadi orang lain,
                // supaya pengurus memeriksanya sebelum dibiarkan tayang.
                'perlu_diperiksa' => Aspirasi::memuatDataPribadi($a->isi),
            ])->all();

        return Inertia::render('Panel/Aspirasi/Index', [
            'daftar' => $daftar,
            'rekap' => $this->aspirasi->rekap(),
            'pilihanStatus' => Aspiration::STATUS,
            'pilihanKategori' => Aspiration::KATEGORI,
            'saringan' => ['status' => $status, 'kategori' => $kategori],
            'bolehLihatIdentitas' => $bolehLihatIdentitas,
            'catatan' => 'Papan publik hanya memuat aspirasi yang sudah ditanggapi, tanpa identitas pengirim, dan badan suratnya sudah dibersihkan dari nomor telepon, email, serta NIK. Tanggapanmu ikut tayang — itulah yang membuat papan ini bukan sekadar tumpukan keluhan.',
        ]);
    }

    public function baca(Request $request, Aspiration $aspirasi): RedirectResponse
    {
        $this->aspirasi->tandaiDibaca($aspirasi, $request->user());

        return back()->with('sukses', 'Aspirasi '.$aspirasi->nomor_tiket.' ditandai sudah dibaca.');
    }

    public function tanggapi(Request $request, Aspiration $aspirasi): RedirectResponse
    {
        $data = $request->validate([
            'tanggapan' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::in([Aspiration::STATUS_DIPROSES, Aspiration::STATUS_SELESAI])],
        ]);

        return $this->jalankan(
            fn () => $this->aspirasi->tanggapi($aspirasi, $data['tanggapan'], $data['status'], $request->user()),
            'Tanggapan tersimpan dan akan tampil di papan publik.',
        );
    }

    public function tutup(Request $request, Aspiration $aspirasi): RedirectResponse
    {
        $data = $request->validate([
            'tanggapan' => ['required', 'string', 'max:5000'],
        ]);

        return $this->jalankan(
            fn () => $this->aspirasi->tutup($aspirasi, $data['tanggapan'], $request->user()),
            'Aspirasi ditutup dengan alasan yang akan dibaca pengirimnya.',
        );
    }

    public function tampil(Request $request, Aspiration $aspirasi): RedirectResponse
    {
        $data = $request->validate(['tampil_publik' => ['required', 'boolean']]);

        return $this->jalankan(
            fn () => $this->aspirasi->aturTampil($aspirasi, (bool) $data['tampil_publik'], $request->user()),
            $data['tampil_publik']
                ? 'Aspirasi ini akan tampil di papan publik setelah ditanggapi.'
                : 'Aspirasi ini disembunyikan dari papan publik.',
        );
    }

    /**
     * Ekspor aspirasi sebagai CSV — termasuk identitas, karena berkasnya
     * diunduh pengurus, bukan ditayangkan.
     *
     * CSV, bukan xlsx: container ini tidak punya ekstensi zip yang dibutuhkan
     * pembuat berkas Excel.
     */
    public function ekspor(Request $request): ResponsHttp
    {
        $bolehLihatIdentitas = $request->user()->can('aspirations.view-identity');

        $baris = Aspiration::query()->orderByDesc('created_at')->limit(5000)->get();

        $bersih = fn (string $teks): string => '"'.str_replace('"', '""', $teks).'"';

        $isi = "\xEF\xBB\xBFsep=;\n";
        $isi .= "Nomor Tiket;Tanggal;Kategori;Judul;Isi;Status;Tanggapan;Ditanggapi Pada;Nama Pengirim;Email;Telepon;Tampil Publik\n";

        foreach ($baris as $a) {
            $isi .= implode(';', [
                $bersih($a->nomor_tiket),
                $bersih($a->created_at?->format('Y-m-d H:i') ?? '-'),
                $bersih($a->labelKategori()),
                $bersih($a->judulTeks()),
                $bersih($a->isi),
                $bersih($a->labelStatus()),
                $bersih((string) $a->tanggapan),
                $bersih($a->ditanggapi_pada?->format('Y-m-d H:i') ?? '-'),
                // Identitas dikosongkan bila pengunduhnya tidak berhak.
                $bersih($bolehLihatIdentitas ? $a->nama_pengirim : '—'),
                $bersih($bolehLihatIdentitas ? $a->email_pengirim : '—'),
                $bersih($bolehLihatIdentitas ? (string) $a->telepon_pengirim : '—'),
                $bersih($a->tampil_publik ? 'Ya' : 'Tidak'),
            ])."\n";
        }

        return response($isi, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="aspirasi.csv"',
        ]);
    }
}
