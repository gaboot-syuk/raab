<script setup lang="ts">
/*
 * Peminjaman saya — kader & alumni.
 *
 * Tidak ada denda di halaman ini dan tidak akan pernah ada: keterlambatan
 * hanya ditampilkan sebagai peringatan.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Pinjaman {
    id: number;
    kode_pinjam: string;
    barang: string;
    label_status: string;
    jatuh_tempo: string | null;
    sedang_dipinjam: boolean;
    terlambat: boolean;
    hari_terlambat: number;
    sisa_hari: number | null;
    boleh_diperpanjang: boolean;
    perpanjangan_ke: number;
    ada_pengajuan_perpanjangan: boolean;
    catatan_petugas: string | null;
}

const props = defineProps<{
    pinjaman: Pinjaman[];
    sedangDipinjam: Pinjaman[];
    riwayat: Pinjaman[];
    antrian: { id: number; judul: string | null; label_status: string; posisi: number; kedaluwarsa_pada: string | null; sisa_jam: number | null }[];
    bisaDipinjam: { id: number; judul: string; penulis: string | null; tersedia: number; untukku: boolean }[];
    batasPerpanjangan: number;
    durasiHari: number;
}>();

const form = useForm({ book_id: null as number | null });

const perpanjangUntuk = ref<number | null>(null);
const formPerpanjang = useForm({ alasan: 'Belum selesai dibaca.' });

function pinjam(): void {
    form.post('/pustaka/pinjam', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function antri(id: number): void {
    router.post('/pustaka/antri', { book_id: id }, { preserveScroll: true });
}

function perpanjang(item: Pinjaman): void {
    // Alasan ditanyakan lewat formulir kecil di bawah baris ini, bukan
    // window.prompt(): prompt diblokir di konteks ber-sandbox dan teksnya
    // tidak pernah terlihat lagi setelah dikirim.
    perpanjangUntuk.value = perpanjangUntuk.value === item.id ? null : item.id;
    formPerpanjang.reset();
    formPerpanjang.clearErrors();
}

function kirimPerpanjang(item: Pinjaman): void {
    formPerpanjang.post(`/pustaka/${item.id}/perpanjang`, {
        preserveScroll: true,
        onSuccess: () => {
            perpanjangUntuk.value = null;
            formPerpanjang.reset();
        },
    });
}

function batalkan(item: Pinjaman): void {
    if (!confirm(`Batalkan pengajuan ${item.kode_pinjam}?`)) {
        return;
    }

    router.post(`/pustaka/${item.id}/batalkan`, {}, { preserveScroll: true });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Pustaka Saya" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Pustaka Saya</h1>
                <p class="mt-1 text-sm text-muted">
                    Masa pinjam {{ props.durasiHari }} hari. Maksimal {{ props.batasPerpanjangan }} perpanjangan per peminjaman.
                </p>
                <p class="mt-2 border-2 border-ink bg-paper-alt p-3 text-sm">
                    Rayon ini <span class="font-bold">tidak menerapkan denda</span> keterlambatan. Keterlambatan hanya kami ingatkan lewat email.
                </p>
            </div>

            <!-- Ajukan pinjaman -->
            <form class="brutal bg-paper p-5" @submit.prevent="pinjam">
                <h2 class="font-display text-lg">Pinjam Buku</h2>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Pilih buku</span>
                        <select v-model="form.book_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— pilih buku yang tersedia —</option>
                            <option v-for="buku in props.bisaDipinjam" :key="buku.id" :value="buku.id">
                                {{ buku.judul }}<template v-if="buku.penulis"> — {{ buku.penulis }}</template>
                                ({{ buku.untukku ? 'siap diambil untukmu' : buku.tersedia + ' tersedia' }})
                            </option>
                        </select>
                        <span v-if="form.errors.book_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.book_id }}</span>
                    </label>

                    <div class="flex items-end">
                        <button type="submit" :disabled="form.processing || !form.book_id" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 disabled:opacity-50">
                            Ajukan Peminjaman
                        </button>
                    </div>
                </div>
            </form>

            <!-- Daftar tunggu -->
            <section v-if="props.antrian.length" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Tunggu</h2>
                <ul class="mt-3 divide-y-2 divide-ink/10 text-sm">
                    <li v-for="a in props.antrian" :key="a.id" class="py-2.5">
                        <p class="font-bold">{{ a.judul }}</p>
                        <p class="text-xs text-muted">
                            Antrian #{{ a.posisi }} · {{ a.label_status }}
                            <template v-if="a.kedaluwarsa_pada">
                                · ambil sebelum {{ a.kedaluwarsa_pada }}
                                <template v-if="a.sisa_jam !== null"> (sisa {{ a.sisa_jam }} jam)</template>
                            </template>
                        </p>
                    </li>
                </ul>
            </section>

            <!-- Sedang dipinjam -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Sedang Dipinjam</h2>

                <p v-if="!props.sedangDipinjam.length" class="mt-3 text-sm text-muted">Tidak ada peminjaman berjalan.</p>

                <ul v-else class="mt-3 space-y-3">
                    <li v-for="item in props.sedangDipinjam" :key="item.id" class="border-2 border-ink p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-bold">{{ item.barang }}</p>
                                <p class="text-xs text-muted">
                                    <span class="font-mono">{{ item.kode_pinjam }}</span> · {{ item.label_status }}
                                    <template v-if="item.jatuh_tempo"> · jatuh tempo {{ item.jatuh_tempo }}</template>
                                </p>
                                <p v-if="item.terlambat" class="mt-1 text-xs font-bold text-accent-600">
                                    Terlambat {{ item.hari_terlambat }} hari
                                </p>
                                <p v-else-if="item.sisa_hari !== null" class="mt-1 text-xs text-muted">
                                    Sisa {{ item.sisa_hari }} hari
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="item.boleh_diperpanjang && !item.ada_pengajuan_perpanjangan"
                                    type="button"
                                    class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                                    @click="perpanjang(item)"
                                >Ajukan Perpanjangan</button>

                                <span v-if="item.ada_pengajuan_perpanjangan" class="border-2 border-ink bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                    Perpanjangan menunggu persetujuan
                                </span>

                                <button
                                    v-if="item.label_status === 'Menunggu Persetujuan'"
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                    @click="batalkan(item)"
                                >Batalkan</button>
                            </div>
                        </div>

                        <form
                            v-if="perpanjangUntuk === item.id"
                            class="mt-3 border-t-2 border-ink/10 pt-3"
                            @submit.prevent="kirimPerpanjang(item)"
                        >
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Alasan memperpanjang</span>
                                <textarea v-model="formPerpanjang.alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                                <span v-if="formPerpanjang.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formPerpanjang.errors.alasan }}</span>
                            </label>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="submit" :disabled="formPerpanjang.processing" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                    Kirim Pengajuan
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="perpanjangUntuk = null">
                                    Batal
                                </button>
                            </div>
                        </form>

                        <p v-if="item.catatan_petugas" class="mt-2 text-xs">
                            <span class="font-bold uppercase">Catatan petugas:</span> {{ item.catatan_petugas }}
                        </p>
                    </li>
                </ul>
            </section>

            <!-- Riwayat -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Riwayat Peminjaman</h2>

                <p v-if="!props.riwayat.length" class="mt-3 text-sm text-muted">Belum ada riwayat.</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Kode</th>
                            <th class="py-2">Yang dipinjam</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in props.riwayat" :key="item.id" class="border-b-2 border-ink/10">
                            <td class="py-2 font-mono text-xs">{{ item.kode_pinjam }}</td>
                            <td class="py-2">{{ item.barang }}</td>
                            <td class="py-2">{{ item.label_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AnggotaLayout>
</template>
