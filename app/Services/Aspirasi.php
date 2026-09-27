<?php

namespace App\Services;

use App\Models\Aspiration;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aspirasi: penerimaan, tindak lanjut, dan penayangan publik.
 *
 * EMPAT JANJI yang dipegang kelas ini:
 *
 * 1. IDENTITAS DISIMPAN, TIDAK DISIARKAN. Nama, email, dan telepon pengirim
 *    tersimpan lengkap supaya pengurus bisa menindaklanjuti — tetapi papan
 *    publik menerima data yang SUDAH DIBERSIHKAN, bukan objek modelnya, dan
 *    tidak memuat nama pengirim sama sekali. Jadi tidak ada jalan bagi kolom
 *    identitas untuk ikut terkirim karena lupa.
 *
 * 2. ISI DIBERSIHKAN SAAT TAYANG, BUKAN SAAT DISIMPAN. Nomor telepon, email,
 *    dan NIK yang ditulis sendiri oleh pengirim di badan surat disamarkan
 *    untuk pembaca publik. Pengurus tetap membaca aslinya — kalau disamarkan
 *    di penyimpanan, surat yang tidak bisa dihubungi jadi tidak bisa
 *    ditindaklanjuti.
 *
 * 3. MELACAK BUTUH TIKET **DAN** TOKEN. Nomor tiket berurutan dan mudah
 *    ditebak; kalau ia sendirian sudah cukup, siapa pun bisa memanen isi surat
 *    orang lain satu per satu.
 *
 * 4. TANGGAPAN RESMI SELALU DISIARKAN bila ada — termasuk tanggal dan
 *    statusnya. Papan aspirasi yang tidak pernah menunjukkan jawaban hanya
 *    mengumpulkan keluhan.
 */
class Aspirasi
{
    /**
     * Tanda pengganti untuk data pribadi yang disamarkan.
     */
    public const TANDA_SAMAR = '[disamarkan]';

    /* ------------------------------------------------------------------ */
    /* Penerimaan                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Terima aspirasi baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function kirim(array $data, ?Member $anggota = null): Aspiration
    {
        if (trim((string) ($data['judul'] ?? '')) === '') {
            throw ValidationException::withMessages(['judul' => 'Judul aspirasi wajib diisi.']);
        }

        $isi = trim((string) ($data['isi'] ?? ''));

        if (mb_strlen($isi) < Aspiration::MIN_ISI) {
            throw ValidationException::withMessages([
                'isi' => 'Tuliskan aspirasimu sedikit lebih lengkap (minimal '.Aspiration::MIN_ISI.' huruf) supaya bisa ditindaklanjuti.',
            ]);
        }

        $aspirasi = new Aspiration;
        $aspirasi->nomor_tiket = Aspiration::nomorTiketBaru();
        $aspirasi->token_lacak = Aspiration::tokenLacakBaru();
        $aspirasi->member_id = $anggota?->id;
        $aspirasi->nama_pengirim = trim((string) $data['nama_pengirim']);
        $aspirasi->email_pengirim = trim((string) $data['email_pengirim']);
        $aspirasi->telepon_pengirim = isset($data['telepon_pengirim']) && trim((string) $data['telepon_pengirim']) !== ''
            ? trim((string) $data['telepon_pengirim'])
            : null;
        $aspirasi->kategori = $data['kategori'];
        $aspirasi->isi = $isi;
        $aspirasi->status = Aspiration::STATUS_BARU;
        // Pengirim boleh memilih tidak disiarkan sama sekali. Menghormati
        // pilihan itu lebih penting daripada menambah isi papan.
        $aspirasi->tampil_publik = (bool) ($data['tampil_publik'] ?? true);

        $aspirasi->setTranslations('judul', ['id' => trim((string) $data['judul'])]);
        $aspirasi->save();

        activity()
            ->performedOn($aspirasi)
            ->withProperties(['kategori' => $aspirasi->kategori])
            ->log('Aspirasi diterima');

        return $aspirasi;
    }

    /* ------------------------------------------------------------------ */
    /* Tindak lanjut                                                       */
    /* ------------------------------------------------------------------ */

    public function tandaiDibaca(Aspiration $aspirasi, User $pengurus): Aspiration
    {
        if ($aspirasi->status !== Aspiration::STATUS_BARU) {
            return $aspirasi;
        }

        $aspirasi->status = Aspiration::STATUS_DIBACA;
        $aspirasi->ditanggapi_oleh = $pengurus->id;
        $aspirasi->save();

        return $aspirasi;
    }

    /**
     * Jawab aspirasi: Sedang Diproses atau Selesai.
     *
     * Tanggapan yang menjelaskan PENOLAKAN tidak lewat sini melainkan lewat
     * `tutup()`. Pemisahan itu bukan sekadar kerapian: "menjawab" dan "menutup
     * tanpa tindak lanjut" adalah dua tindakan berbeda, dan izinnya pun
     * berbeda (`aspirations.reply` vs `aspirations.close`).
     */
    public function tanggapi(Aspiration $aspirasi, string $tanggapan, string $status, User $pengurus): Aspiration
    {
        if (! in_array($status, [Aspiration::STATUS_DIPROSES, Aspiration::STATUS_SELESAI], true)) {
            throw ValidationException::withMessages([
                'status' => 'Tanggapan hanya untuk status Sedang Diproses atau Selesai. Untuk tidak menindaklanjuti, gunakan Tutup beserta alasannya.',
            ]);
        }

        return $this->simpanTanggapan($aspirasi, $tanggapan, $status, $pengurus);
    }

    /**
     * Tutup aspirasi sebagai tidak dapat ditindaklanjuti — WAJIB beralasan.
     *
     * Alasannya DISIARKAN di papan publik. Pengirim berhak tahu mengapa
     * aspirasinya berhenti; yang tidak boleh adalah aspirasi yang hilang tanpa
     * kabar.
     */
    public function tutup(Aspiration $aspirasi, string $alasan, User $pengurus): Aspiration
    {
        if (trim($alasan) === '') {
            throw ValidationException::withMessages([
                'tanggapan' => 'Alasan wajib diisi — aspirasi yang ditutup tanpa keterangan tidak bisa dipertanggungjawabkan.',
            ]);
        }

        return $this->simpanTanggapan($aspirasi, $alasan, Aspiration::STATUS_DITOLAK, $pengurus);
    }

    /**
     * Jantung kedua tindakan di atas: menulis tanggapan lalu menyimpannya.
     */
    private function simpanTanggapan(Aspiration $aspirasi, string $tanggapan, string $status, User $pengurus): Aspiration
    {
        $tanggapan = trim($tanggapan);

        if ($tanggapan === '') {
            throw ValidationException::withMessages([
                'tanggapan' => 'Tanggapan wajib diisi — status tanpa penjelasan tidak memberi tahu pengirim apa pun.',
            ]);
        }

        return DB::transaction(function () use ($aspirasi, $tanggapan, $status, $pengurus): Aspiration {
            $aspirasi->tanggapan = $tanggapan;
            $aspirasi->status = $status;
            $aspirasi->ditanggapi_oleh = $pengurus->id;
            $aspirasi->ditanggapi_pada = now();
            $aspirasi->save();

            activity()
                ->performedOn($aspirasi)
                ->withProperties(['status' => $status, 'oleh' => $pengurus->id])
                ->log('Aspirasi ditanggapi');

            return $aspirasi;
        }, 3);
    }

    /**
     * Atur penayangan di papan publik.
     *
     * Dipakai untuk aspirasi yang isinya memuat data pribadi orang lain —
     * redaksi otomatis menangkap nomor dan email, tetapi tidak bisa mengenali
     * nama. Untuk kasus begitu, pengurus yang memutuskan.
     */
    public function aturTampil(Aspiration $aspirasi, bool $tampil, User $pengurus): Aspiration
    {
        $aspirasi->tampil_publik = $tampil;
        $aspirasi->save();

        activity()
            ->performedOn($aspirasi)
            ->withProperties(['oleh' => $pengurus->id, 'tampil' => $tampil])
            ->log($tampil ? 'Aspirasi ditayangkan' : 'Aspirasi disembunyikan dari papan publik');

        return $aspirasi;
    }

    /* ------------------------------------------------------------------ */
    /* Pelacakan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Cari aspirasi berdasarkan nomor tiket DAN token.
     *
     * Keduanya diperlukan. Nomor tiket berurutan, jadi ia tidak boleh cukup
     * sendirian untuk membuka isi surat orang lain.
     */
    public function lacak(string $nomorTiket, string $token): ?Aspiration
    {
        $nomorTiket = strtoupper(trim($nomorTiket));
        $token = trim($token);

        if ($nomorTiket === '' || $token === '') {
            return null;
        }

        return Aspiration::query()
            ->where('nomor_tiket', $nomorTiket)
            ->where('token_lacak', $token)
            ->first();
    }

    /* ------------------------------------------------------------------ */
    /* Papan publik                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Data papan publik — SUDAH BERSIH.
     *
     * Metode ini mengembalikan array, bukan model. Itu disengaja: selama yang
     * dikirim ke tampilan berupa array pilihan, tidak ada cara bagi kolom
     * identitas untuk ikut bocor karena seseorang menambahkan
     * `->with('anggota')` di kemudian hari.
     *
     * @return array<int, array<string, mixed>>
     */
    public function papan(?string $kategori = null, int $batas = 60): array
    {
        return Aspiration::query()
            // Hanya yang SUDAH ditanggapi. Papan yang menampilkan keluhan tanpa
            // jawaban terbaca seperti rayon yang tidak pernah menanggapi apa pun.
            ->whereNotNull('tanggapan')
            ->papanPublik()
            ->when($kategori, fn ($q) => $q->kategori($kategori))
            ->orderByDesc('ditanggapi_pada')
            ->limit($batas)
            ->get()
            ->map(fn (Aspiration $a): array => [
                'judul' => $a->judulTeks(),
                'isi' => self::redaksi($a->isi),
                'kategori' => $a->labelKategori(),
                'status' => $a->status,
                'label_status' => $a->labelStatus(),
                'tanggapan' => self::redaksi((string) $a->tanggapan),
                'ditanggapi_pada' => $a->ditanggapi_pada?->translatedFormat('d F Y'),
                /*
                 * NAMA PENGIRIM TIDAK PERNAH IKUT KE SINI. Dokumen alur bisnis
                 * menetapkan papan publik hanya memuat nomor tiket, kategori,
                 * isi, dan tanggapan resmi — bukan siapa yang mengirim. Nama
                 * yang dipajang membuat orang enggan menyampaikan keluhan yang
                 * benar, dan bisa mendatangkan masalah bagi pengirimnya.
                 */
                'tanggal' => $a->created_at?->translatedFormat('d F Y'),
            ])->all();
    }

    /**
     * Ringkasan untuk kepala halaman papan publik.
     *
     * @return array{total: int, selesai: int, diproses: int}
     */
    public function rekapPublik(): array
    {
        $ditanggapi = Aspiration::query()->whereNotNull('tanggapan')->papanPublik()->get();

        return [
            'total' => $ditanggapi->count(),
            'selesai' => $ditanggapi->where('status', Aspiration::STATUS_SELESAI)->count(),
            'diproses' => $ditanggapi->where('status', Aspiration::STATUS_DIPROSES)->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Redaksi data pribadi                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Samarkan data pribadi yang ditulis di badan surat.
     *
     * Yang disasar: alamat email, nomor telepon Indonesia, NIK (16 angka), dan
     * deretan angka panjang (rekening/NIM). Nomor telepon sengaja ditangani
     * lebih dulu daripada deretan angka umum, supaya "0812-3456-7890" tidak
     * tersisa sebagian.
     *
     * BATAS KEMAMPUANNYA: nama orang tidak bisa dikenali dengan cara ini.
     * Karena itu ada sakelar `tampil_publik` untuk pengurus.
     */
    public static function redaksi(?string $teks): ?string
    {
        if ($teks === null || $teks === '') {
            return $teks;
        }

        // Email.
        $teks = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', self::TANDA_SAMAR, $teks) ?? $teks;

        // Nomor telepon Indonesia: 08xx…, 628xx…, +62 8xx… (dengan pemisah bebas).
        $teks = preg_replace('/(?:\+?62|0)8[\d\-\s.]{6,14}\d/u', self::TANDA_SAMAR, $teks) ?? $teks;

        // Deretan angka panjang tanpa pemisah: NIK 16 digit, nomor rekening, NIM.
        $teks = preg_replace('/\b\d{10,}\b/u', self::TANDA_SAMAR, $teks) ?? $teks;

        return $teks;
    }

    /**
     * Apakah teks ini memuat data pribadi?
     *
     * Dipakai panel untuk menandai aspirasi yang berkasnya perlu diperiksa
     * sebelum ditayangkan, bukan untuk memblokir.
     */
    public static function memuatDataPribadi(?string $teks): bool
    {
        if ($teks === null || $teks === '') {
            return false;
        }

        return self::redaksi($teks) !== $teks;
    }

    /* ------------------------------------------------------------------ */
    /* Panel                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Rekap untuk panel pengurus.
     *
     * @return array{baru: int, dibaca: int, diproses: int, selesai: int, ditolak: int, total: int, belum_ditanggapi: int}
     */
    public function rekap(): array
    {
        $semua = Aspiration::query()->get();

        return [
            'baru' => $semua->where('status', Aspiration::STATUS_BARU)->count(),
            'dibaca' => $semua->where('status', Aspiration::STATUS_DIBACA)->count(),
            'diproses' => $semua->where('status', Aspiration::STATUS_DIPROSES)->count(),
            'selesai' => $semua->where('status', Aspiration::STATUS_SELESAI)->count(),
            'ditolak' => $semua->where('status', Aspiration::STATUS_DITOLAK)->count(),
            'total' => $semua->count(),
            'belum_ditanggapi' => $semua->filter(fn (Aspiration $a): bool => ! $a->sudahDitanggapi())->count(),
        ];
    }

    /**
     * Aspirasi dengan antrean kerja: yang belum ditanggapi di atas.
     *
     * @return Collection<int, Aspiration>
     */
    public function antrean(?string $status = null, ?string $kategori = null, int $batas = 150): Collection
    {
        return Aspiration::query()
            ->when($status, fn ($q) => $q->status($status))
            ->when($kategori, fn ($q) => $q->kategori($kategori))
            // Yang belum ditanggapi paling atas — hampir semua pekerjaan di
            // halaman ini adalah menanggapi yang baru masuk.
            ->orderByRaw('CASE WHEN tanggapan IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->limit($batas)
            ->get();
    }
}
