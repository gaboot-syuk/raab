<script setup lang="ts">
/*
 * Poin kontribusi milik kader sendiri.
 *
 * POSISI di papan peringkat dan RINCIAN ASAL poin ditampilkan bersama. Yang
 * pertama memotivasi, yang kedua yang membuat angkanya bisa dipercaya —
 * tanpa rinciannya, angka poin hanya jadi kotak hitam.
 *
 * Baris poin yang DIBATALKAN tetap ditampilkan. Menyembunyikannya membuat
 * dua angka poin kader berbeda tanpa penjelasan apa pun.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Catatan {
    id: number;
    sumber: string;
    label_sumber: string;
    poin: number;
    dibatalkan: boolean;
    alasan_pembatalan: string | null;
    keterangan: string;
    terjadi_pada: string | null;
    periode: string;
}

const props = defineProps<{
    anggota: boolean;
    total: number;
    posisi: number | null;
    rincianSumber: { sumber: string; label: string; poin: number; jumlah: number }[];
    pilihanPeriode?: string[];
    periode?: string | null;
    catatan: Catatan[];
    catatanHalaman?: string;
}>();

const periodePilih = ref(props.periode ?? '');

function gantiPeriode(): void {
    router.get('/kontribusi', { periode: periodePilih.value }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Poin Kontribusi" />

    <AnggotaLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Poin Kontribusi</h1>
                    <p class="text-sm text-muted">Poin yang kamu kumpulkan dari kehadiran dan karya.</p>
                </div>

                <label v-if="props.anggota" class="block">
                    <span class="text-xs font-bold uppercase text-muted">Periode</span>
                    <select v-model="periodePilih" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="gantiPeriode">
                        <option value="">Semua periode</option>
                        <option v-for="p in props.pilihanPeriode" :key="p" :value="p">{{ p }}</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.anggota" class="brutal bg-paper p-5 text-sm text-muted">
                Akunmu belum terhubung ke data anggota, jadi poin belum bisa ditampilkan.
            </p>

            <template v-else>
                <!-- ===== Ringkasan ===== -->
                <section class="brutal bg-brand-dark p-5 text-on-brand">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-brand/70">Total Poin</p>
                    <p class="mt-2 font-display text-4xl">{{ props.total }}</p>

                    <p class="mt-2 text-sm text-on-brand/85">
                        <span v-if="props.posisi">Peringkat <strong>#{{ props.posisi }}</strong> di antara kader aktif.</span>
                        <span v-else>Belum masuk papan peringkat — poinmu muncul begitu kamu ikut kegiatan.</span>
                    </p>
                </section>

                <!-- ===== Rincian sumber ===== -->
                <section class="grid gap-3 sm:grid-cols-3">
                    <div v-for="s in props.rincianSumber" :key="s.sumber" class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">{{ s.label }}</p>
                        <p class="font-display text-2xl">{{ s.poin }}</p>
                        <p class="text-[11px] text-muted">{{ s.jumlah }} catatan</p>
                    </div>
                </section>

                <p v-if="props.catatanHalaman" class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatanHalaman }}</p>

                <!-- ===== Buku besar ===== -->
                <section>
                    <h2 class="font-display text-lg">Rincian Poinmu</h2>

                    <p v-if="!props.catatan.length" class="mt-2 text-sm text-muted">
                        Belum ada catatan poin untuk periode ini.
                    </p>

                    <ul v-else class="mt-3 space-y-2">
                        <li
                            v-for="c in props.catatan"
                            :key="c.id"
                            class="brutal flex flex-wrap items-baseline justify-between gap-2 bg-paper px-4 py-3"
                            :class="c.dibatalkan ? 'opacity-60' : ''"
                        >
                            <span class="min-w-0">
                                <span class="font-bold">{{ c.keterangan }}</span>
                                <span class="block text-[11px] text-muted">
                                    {{ c.label_sumber }} · {{ c.terjadi_pada ?? '—' }} · {{ c.periode }}
                                </span>
                                <span v-if="c.dibatalkan" class="block text-[11px] font-bold text-accent-600">
                                    Dibatalkan: {{ c.alasan_pembatalan }}
                                </span>
                            </span>

                            <span class="font-display text-lg" :class="c.dibatalkan ? 'line-through' : ''">
                                {{ c.poin > 0 ? `+${c.poin}` : c.poin }}
                            </span>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </AnggotaLayout>
</template>
