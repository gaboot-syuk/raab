<script setup lang="ts">
/*
 * Iuran saya — kader, pengurus, dan alumni.
 *
 * Halaman ini hanya MENGIRIM bukti. Yang mengubah saldo kas adalah verifikasi
 * Bendahara, bukan unggahan di sini — jadi tidak ada uang yang bisa "masuk
 * sendiri" tanpa diperiksa.
 *
 * Alasan penolakan dari Bendahara ditampilkan apa adanya supaya anggota tahu
 * apa yang harus diperbaiki, bukan sekadar diminta mengunggah ulang.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Pembayaran {
    id: number;
    jumlah: number;
    metode: string;
    status: string;
    catatan_bendahara: string | null;
    dibuat: string;
}

interface Tagihan {
    id: number;
    kategori: string | null;
    periode_label: string;
    nominal: number;
    status: string;
    label_status: string;
    jatuh_tempo: string | null;
    terlambat: boolean;
    tuntas: boolean;
    dibebaskan_alasan: string | null;
    pembayaran: Pembayaran[];
}

const props = defineProps<{
    tagihan: Tagihan[];
    ringkasan: Record<string, number>;
    batasMb: number;
    catatan: string;
}>();

const bukaUntuk = ref<number | null>(null);

const form = useForm({
    jumlah: 0,
    catatan_pembayar: '',
    bukti: null as File | null,
});

function rupiah(nilai: number | null): string {
    return 'Rp' + (nilai ?? 0).toLocaleString('id-ID');
}

function buka(t: Tagihan): void {
    bukaUntuk.value = bukaUntuk.value === t.id ? null : t.id;
    form.reset();
    form.clearErrors();
    form.jumlah = t.nominal;
}

function pilihBerkas(e: Event): void {
    const berkas = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.bukti = berkas;
}

function kirim(t: Tagihan): void {
    form.post(`/iuran/${t.id}/bukti`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            bukaUntuk.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Iuran Saya" />

        <div class="mx-auto max-w-5xl space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Iuran Saya</h1>
                <p class="mt-1 max-w-3xl text-sm text-muted">{{ props.catatan }}</p>
            </div>

            <ul class="grid gap-3 sm:grid-cols-4">
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Belum Dibayar</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.belum_bayar }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Menunggu Diperiksa</p>
                    <p class="font-display text-2xl">{{ props.ringkasan.menunggu }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Tunggakan</p>
                    <p class="font-display text-2xl">{{ rupiah(props.ringkasan.tunggakan) }}</p>
                </li>
                <li class="brutal bg-paper p-4">
                    <p class="text-xs font-bold uppercase text-muted">Terlambat</p>
                    <p class="font-display text-2xl text-accent-600">{{ props.ringkasan.terlambat }}</p>
                </li>
            </ul>

            <p v-if="!props.tagihan.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                Belum ada tagihan iuran untukmu. Tagihan akan muncul di sini begitu Bendahara menerbitkannya.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="t in props.tagihan" :key="t.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-bold">
                                {{ t.kategori }}
                                <span :class="['ml-2 border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', t.tuntas ? 'bg-paper-alt' : 'bg-accent-100']">
                                    {{ t.label_status }}
                                </span>
                                <span v-if="t.terlambat" class="ml-1 border-2 border-ink bg-accent-100 px-2 py-0.5 text-[10px] font-bold uppercase">
                                    Terlambat
                                </span>
                            </p>
                            <p class="mt-1 text-sm">
                                Periode {{ t.periode_label }} · <span class="font-display">{{ rupiah(t.nominal) }}</span>
                                <template v-if="t.jatuh_tempo"> · jatuh tempo {{ t.jatuh_tempo }}</template>
                            </p>
                            <p v-if="t.dibebaskan_alasan" class="mt-1 text-xs text-muted">
                                Dibebaskan oleh Bendahara: {{ t.dibebaskan_alasan }}
                            </p>

                            <!-- Riwayat unggahan, termasuk yang ditolak -->
                            <ul v-if="t.pembayaran.length" class="mt-2 space-y-1">
                                <li v-for="p in t.pembayaran" :key="p.id" class="text-xs">
                                    <span class="font-mono">{{ rupiah(p.jumlah) }}</span> · {{ p.metode }} · {{ p.status }} · {{ p.dibuat }}
                                    <span v-if="p.catatan_bendahara" class="block font-bold text-accent-600">
                                        Catatan Bendahara: {{ p.catatan_bendahara }}
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <button
                            v-if="!t.tuntas && t.status !== 'menunggu'"
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                            @click="buka(t)"
                        >
                            {{ bukaUntuk === t.id ? 'Tutup' : 'Kirim Bukti Transfer' }}
                        </button>
                        <span v-else-if="t.status === 'menunggu'" class="border-2 border-ink bg-paper-alt px-3 py-1.5 text-xs font-bold">
                            Menunggu diperiksa Bendahara
                        </span>
                    </div>

                    <form v-if="bukaUntuk === t.id" class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 sm:grid-cols-2" @submit.prevent="kirim(t)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Jumlah ditransfer (Rp) *</span>
                            <input v-model.number="form.jumlah" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="form.errors.jumlah" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.jumlah }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Bukti transfer *</span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                                @change="pilihBerkas"
                            >
                            <span class="text-[11px] text-muted">JPG, PNG, WebP, atau PDF. Maksimal {{ props.batasMb }} MB.</span>
                            <span v-if="form.errors.bukti" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.bukti }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Catatan (opsional)</span>
                            <input v-model="form.catatan_pembayar" type="text" placeholder="mis. transfer dari rekening orang tua" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                Kirim Bukti
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="bukaUntuk = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </AnggotaLayout>
</template>
