<script setup lang="ts">
/*
 * Laporan keuangan.
 *
 * Semua angka dihitung dari transaksi terkonfirmasi — draft dan void tidak
 * pernah masuk hitungan. Karena itu laporan tidak mungkin berbeda dari buku kas.
 *
 * Ekspor memakai CSV: container ini tidak punya ekstensi zip yang dibutuhkan
 * penulis .xlsx, dan CSV tetap terbuka rapi di Excel/LibreOffice/Sheets.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface BarisKas {
    id: number;
    tanggal: string;
    nomor_voucher: string;
    akun: string;
    kategori: string;
    jenis: string;
    label_jenis: string;
    sumber: string;
    keterangan: string;
    jumlah: number;
    saldo_sesudah: number | null;
}

const props = defineProps<{
    saring: { akun: string; dari: string; sampai: string; periode: string };
    pilihanAkun: { id: number; nama: string; saldo: number }[];
    pilihanPeriode: { id: number; nama: string }[];
    bukuKas: BarisKas[];
    arusBulanan: { bulan: string; label: string; masuk: number; keluar: number; selisih: number }[];
    rekapKategori: { kategori: string; label_jenis: string; total: number; jumlah_transaksi: number }[];
    anggaran: { id: number; nama: string; label_jenis: string; rencana: number; realisasi: number; sisa: number; persen: number; melebihi: boolean; kegiatan: string | null }[];
    ringkasan: Record<string, number>;
    catatan: string;
}>();

const saring = ref({ ...props.saring });

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function terapkan(): void {
    router.get('/panel/keuangan/laporan', { ...saring.value }, { preserveState: true, preserveScroll: true });
}

function ekspor(): void {
    const q = new URLSearchParams({ ...saring.value }).toString();
    window.open(`/panel/keuangan/laporan/ekspor?${q}`, '_blank');
}
</script>

<template>
    <PanelLayout>
        <Head title="Laporan Keuangan" />

        <div class="mx-auto max-w-6xl space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Laporan Keuangan</h1>
                    <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="ekspor">
                        Ekspor CSV
                    </button>
                    <a href="/panel/keuangan" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">Ikhtisar</a>
                </div>
            </div>

            <!-- Saringan -->
            <form class="brutal grid gap-3 bg-paper p-5 sm:grid-cols-4" @submit.prevent="terapkan">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Dari</span>
                    <input v-model="saring.dari" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Sampai</span>
                    <input v-model="saring.sampai" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Akun</span>
                    <select v-model="saring.akun" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua akun</option>
                        <option v-for="a in props.pilihanAkun" :key="a.id" :value="String(a.id)">{{ a.nama }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Periode anggaran</span>
                    <select v-model="saring.periode" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <option value="">Semua periode</option>
                        <option v-for="p in props.pilihanPeriode" :key="p.id" :value="String(p.id)">{{ p.nama }}</option>
                    </select>
                </label>

                <div class="flex items-end sm:col-span-4">
                    <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Terapkan</button>
                </div>
            </form>

            <!-- Ringkasan -->
            <ul class="grid gap-3 sm:grid-cols-4">
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Saldo Awal</p>
                    <p class="font-display text-xl">{{ rupiah(props.ringkasan.saldo_awal) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Total Masuk</p>
                    <p class="font-display text-xl">{{ rupiah(props.ringkasan.total_masuk) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Total Keluar</p>
                    <p class="font-display text-xl text-accent-600">{{ rupiah(props.ringkasan.total_keluar) }}</p>
                </li>
                <li class="brutal bg-accent-100 p-4">
                    <p class="text-xs font-bold uppercase text-muted">Saldo Akhir</p>
                    <p class="font-display text-xl">{{ rupiah(props.ringkasan.saldo_akhir) }}</p>
                </li>
            </ul>

            <!-- Arus bulanan -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Arus Kas Bulanan</h2>
                <p v-if="!props.arusBulanan.length" class="mt-3 text-sm text-muted">Belum ada pergerakan pada rentang ini.</p>
                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Bulan</th>
                            <th class="py-2 text-right">Masuk</th>
                            <th class="py-2 text-right">Keluar</th>
                            <th class="py-2 text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in props.arusBulanan" :key="b.bulan" class="border-b-2 border-ink/10">
                            <td class="py-2">{{ b.label }}</td>
                            <td class="py-2 text-right">{{ rupiah(b.masuk) }}</td>
                            <td class="py-2 text-right">{{ rupiah(b.keluar) }}</td>
                            <td class="py-2 text-right" :class="b.selisih < 0 ? 'text-accent-600' : ''">{{ rupiah(b.selisih) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Rekap kategori -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Rekap per Kategori</h2>
                <p v-if="!props.rekapKategori.length" class="mt-3 text-sm text-muted">Belum ada transaksi pada rentang ini.</p>
                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Kategori</th>
                            <th class="py-2">Jenis</th>
                            <th class="py-2 text-right">Transaksi</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="k in props.rekapKategori" :key="k.kategori + k.label_jenis" class="border-b-2 border-ink/10">
                            <td class="py-2">{{ k.kategori }}</td>
                            <td class="py-2 text-xs">{{ k.label_jenis }}</td>
                            <td class="py-2 text-right text-xs">{{ k.jumlah_transaksi }}</td>
                            <td class="py-2 text-right">{{ rupiah(k.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Anggaran vs realisasi -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Anggaran vs Realisasi</h2>
                <p v-if="!props.anggaran.length" class="mt-3 text-sm text-muted">Belum ada anggaran.</p>
                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Anggaran</th>
                            <th class="py-2 text-right">Rencana</th>
                            <th class="py-2 text-right">Realisasi</th>
                            <th class="py-2 text-right">Sisa</th>
                            <th class="py-2 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in props.anggaran" :key="a.id" class="border-b-2 border-ink/10">
                            <td class="py-2">
                                {{ a.nama }}
                                <span v-if="a.kegiatan" class="block text-xs text-muted">{{ a.kegiatan }}</span>
                                <span v-if="a.melebihi" class="text-[10px] font-bold uppercase text-accent-600">Lewat rencana</span>
                            </td>
                            <td class="py-2 text-right">{{ rupiah(a.rencana) }}</td>
                            <td class="py-2 text-right">{{ rupiah(a.realisasi) }}</td>
                            <td class="py-2 text-right" :class="a.sisa < 0 ? 'text-accent-600' : ''">{{ rupiah(a.sisa) }}</td>
                            <td class="py-2 text-right">{{ a.persen }}%</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Buku kas -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Buku Kas ({{ props.bukuKas.length }} baris)</h2>
                <p v-if="!props.bukuKas.length" class="mt-3 text-sm text-muted">Belum ada transaksi terkonfirmasi pada rentang ini.</p>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                            <tr>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2">Voucher</th>
                                <th class="py-2">Keterangan</th>
                                <th class="py-2">Akun</th>
                                <th class="py-2 text-right">Masuk</th>
                                <th class="py-2 text-right">Keluar</th>
                                <th class="py-2 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in props.bukuKas" :key="b.id" class="border-b-2 border-ink/10">
                                <td class="py-2 text-xs">{{ b.tanggal }}</td>
                                <td class="py-2 font-mono text-xs">{{ b.nomor_voucher }}</td>
                                <td class="py-2">
                                    {{ b.keterangan }}
                                    <span class="block text-xs text-muted">{{ b.sumber }} · {{ b.kategori }}</span>
                                </td>
                                <td class="py-2 text-xs">{{ b.akun }}</td>
                                <td class="py-2 text-right">{{ b.jenis === 'masuk' ? rupiah(b.jumlah) : '' }}</td>
                                <td class="py-2 text-right">{{ b.jenis === 'keluar' ? rupiah(b.jumlah) : '' }}</td>
                                <td class="py-2 text-right text-xs">{{ b.saldo_sesudah !== null ? rupiah(b.saldo_sesudah) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </PanelLayout>
</template>
