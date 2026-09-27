<script setup lang="ts">
/*
 * Ikhtisar keuangan.
 *
 * Halaman ini sengaja menonjolkan Pekerjaan Yang Menunggu — draft, bukti iuran
 * yang belum diverifikasi, anggaran yang lewat rencana — bukan sekadar total.
 * Angka besar tanpa tindakan hanya membuat Bendahara menebak harus mulai dari mana.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Akun {
    id: number;
    kode: string;
    nama: string;
    jenis: string;
    label_jenis: string;
    saldo_awal: number;
    saldo_berjalan: number;
    aktif: boolean;
    saldo_sehat: boolean;
}

interface Transaksi {
    id: number;
    nomor_voucher: string;
    tanggal: string;
    label_jenis: string;
    jenis: string;
    jumlah: number;
    keterangan: string;
    sumber: string;
    akun: string;
    kategori: string | null;
    label_status: string;
    status: string;
    tanpa_bukti: boolean;
}

const props = defineProps<{
    akun: Akun[];
    totalSaldo: number;
    ringkasan: Record<string, number>;
    bulan: string;
    pilihanJenisAkun: Record<string, string>;
    transaksiTerakhir: Transaksi[];
    anggaranMelebihi: { id: number; nama: string; rencana: number; realisasi: number }[];
    jumlahKategori: number;
    catatan: string;
}>();

const bukaAkun = ref(false);

const form = useForm({
    kode: '',
    nama: '',
    jenis: 'utama',
    saldo_awal: 0,
    urutan: 0,
});

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function simpanAkun(): void {
    form.post('/panel/keuangan/akun', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            bukaAkun.value = false;
        },
    });
}
</script>

<template>
    <PanelLayout>
        <Head title="Keuangan" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Keuangan</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="/panel/keuangan/transaksi" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Buku Kas</a>
                    <a href="/panel/keuangan/laporan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Laporan</a>
                </div>
            </div>

            <!-- Saldo tiap akun -->
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="a in props.akun" :key="a.id" class="brutal bg-paper p-5">
                    <p class="text-xs font-bold uppercase text-muted">{{ a.label_jenis }} · {{ a.kode }}</p>
                    <p class="font-bold">{{ a.nama }}</p>
                    <p class="mt-2 font-display text-2xl">{{ rupiah(a.saldo_berjalan) }}</p>
                    <p v-if="!a.saldo_sehat" class="mt-1 border-2 border-ink bg-accent-100 px-2 py-1 text-[11px] font-bold">
                        Saldo tidak cocok dengan buku kas — periksa!
                    </p>
                    <p v-if="!a.aktif" class="mt-1 text-[11px] font-bold uppercase text-muted">Nonaktif</p>
                </li>

                <li v-if="!props.akun.length" class="brutal bg-paper-alt p-5 text-sm text-muted">
                    Belum ada akun kas. Buat akun dulu (mis. Kas Utama) sebelum mencatat transaksi.
                </li>
            </ul>

            <div class="brutal flex flex-wrap items-center justify-between gap-3 bg-paper p-5">
                <div>
                    <p class="text-xs font-bold uppercase text-muted">Total seluruh akun</p>
                    <p class="font-display text-3xl">{{ rupiah(props.totalSaldo) }}</p>
                </div>
                <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="bukaAkun = !bukaAkun">
                    {{ bukaAkun ? 'Tutup' : 'Tambah Akun Kas' }}
                </button>
            </div>

            <!-- Tambah akun -->
            <form v-if="bukaAkun" class="brutal grid gap-4 bg-paper p-5 sm:grid-cols-3" @submit.prevent="simpanAkun">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Kode *</span>
                    <input v-model="form.kode" type="text" placeholder="KAS-01" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.kode" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.kode }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Nama *</span>
                    <input v-model="form.nama" type="text" placeholder="Kas Utama" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                    <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option v-for="(label, nilai) in props.pilihanJenisAkun" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Saldo awal (Rp) *</span>
                    <input v-model.number="form.saldo_awal" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <span class="text-[11px] text-muted">Saldo awal tidak dapat diubah setelah akun dibuat.</span>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                    <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <div class="flex items-end">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Simpan Akun
                    </button>
                </div>
            </form>

            <!-- Ringkasan yang perlu dikerjakan -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Perlu Dikerjakan</h2>
                <ul class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                    <li class="border-2 border-ink p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.draft }}</p>
                        <p class="font-bold">Transaksi masih draft</p>
                        <a href="/panel/keuangan/transaksi?status=draft" class="mt-1 inline-block text-xs underline">Konfirmasi sekarang</a>
                    </li>
                    <li class="border-2 border-ink p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.iuran_menunggu }}</p>
                        <p class="font-bold">Bukti iuran menunggu verifikasi</p>
                        <a href="/panel/keuangan/iuran" class="mt-1 inline-block text-xs underline">Periksa bukti</a>
                    </li>
                    <li class="border-2 border-ink p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.hibah_diajukan }}</p>
                        <p class="font-bold">Hibah menunggu tinjauan</p>
                        <a href="/panel/keuangan/hibah" class="mt-1 inline-block text-xs underline">Tinjau hibah</a>
                    </li>
                </ul>

                <h3 class="mt-5 font-bold">Arus {{ props.bulan }}</h3>
                <p class="text-sm">
                    Masuk <span class="font-display">{{ rupiah(props.ringkasan.masuk_bulan_ini) }}</span>
                    · Keluar <span class="font-display">{{ rupiah(props.ringkasan.keluar_bulan_ini) }}</span>
                    · Selisih
                    <span class="font-display">{{ rupiah(props.ringkasan.masuk_bulan_ini - props.ringkasan.keluar_bulan_ini) }}</span>
                </p>
                <p class="mt-1 text-xs text-muted">{{ props.ringkasan.tagihan_belum }} tagihan iuran belum tuntas.</p>
            </section>

            <!-- Anggaran yang lewat rencana -->
            <section v-if="props.anggaranMelebihi.length" class="brutal bg-accent-100 p-5">
                <h2 class="font-display text-lg">Anggaran Lewat Rencana</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <li v-for="a in props.anggaranMelebihi" :key="a.id">
                        <span class="font-bold">{{ a.nama }}</span> — rencana {{ rupiah(a.rencana) }}, realisasi {{ rupiah(a.realisasi) }}
                    </li>
                </ul>
                <a href="/panel/keuangan/anggaran" class="mt-2 inline-block text-xs underline">Buka anggaran</a>
            </section>

            <!-- Transaksi terakhir -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Transaksi Terakhir</h2>

                <p v-if="!props.transaksiTerakhir.length" class="mt-3 text-sm text-muted">Belum ada transaksi.</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Tanggal</th>
                            <th class="py-2">Voucher</th>
                            <th class="py-2">Keterangan</th>
                            <th class="py-2">Akun</th>
                            <th class="py-2 text-right">Jumlah</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in props.transaksiTerakhir" :key="t.id" class="border-b-2 border-ink/10">
                            <td class="py-2 text-xs">{{ t.tanggal }}</td>
                            <td class="py-2 font-mono text-xs">{{ t.nomor_voucher }}</td>
                            <td class="py-2">
                                {{ t.keterangan }}
                                <span class="block text-xs text-muted">{{ t.sumber }}<template v-if="t.kategori"> · {{ t.kategori }}</template></span>
                            </td>
                            <td class="py-2 text-xs">{{ t.akun }}</td>
                            <td class="py-2 text-right" :class="t.jenis === 'masuk' ? '' : 'text-accent-600'">
                                {{ t.jenis === 'masuk' ? '+' : '−' }}{{ rupiah(t.jumlah) }}
                            </td>
                            <td class="py-2">
                                <span :class="['border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', t.status === 'terkonfirmasi' ? 'bg-paper-alt' : 'bg-accent-100']">
                                    {{ t.label_status }}
                                </span>
                                <span v-if="t.tanpa_bukti" class="ml-1 text-[10px] text-muted">tanpa bukti</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <a href="/panel/keuangan/transaksi" class="mt-3 inline-block text-xs underline">Lihat semua transaksi</a>
            </section>
        </div>
    </PanelLayout>
</template>
