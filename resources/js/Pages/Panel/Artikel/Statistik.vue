<script setup lang="ts">
/*
 * Statistik publikasi (internal) — hanya untuk pengelola konten.
 * Membantu menilai tipe apa yang paling dibaca dan siapa yang paling produktif.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    ringkasan: {
        artikel: number;
        terbit: number;
        draf: number;
        menunggu: number;
        total_dibaca: number;
        rata_dibaca: number;
        belum_terjemah: number;
    };
    terpopuler: { id: number; judul: string | null; label_tipe: string; penulis: string | null; dilihat: number }[];
    perPenulis: { penulis: string; jumlah: number; jumlah_terbit: number; total_dibaca: number }[];
    perTipe: { tipe: string; jumlah: number; total_dibaca: number }[];
}>();

function angka(nilai: number): string {
    return new Intl.NumberFormat('id-ID').format(nilai);
}
</script>

<template>
    <PanelLayout>
        <Head title="Statistik Publikasi" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Statistik Publikasi</h1>
                    <p class="mt-1 text-sm text-muted">
                        Ringkasan jumlah artikel, keterbacaan, dan produktivitas penulis.
                    </p>
                </div>

                <Link href="/panel/artikel" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                    ← Daftar Artikel
                </Link>
            </div>

            <!-- Kartu ringkasan -->
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <li class="brutal bg-paper p-5">
                    <p class="font-display text-3xl">{{ angka(ringkasan.artikel) }}</p>
                    <p class="mt-1 text-sm font-bold">Total Artikel</p>
                </li>
                <li class="brutal bg-paper p-5">
                    <p class="font-display text-3xl">{{ angka(ringkasan.terbit) }}</p>
                    <p class="mt-1 text-sm font-bold">Sudah Terbit</p>
                    <p class="mt-1 text-xs text-muted">{{ angka(ringkasan.draf) }} draf · {{ angka(ringkasan.menunggu) }} menunggu review</p>
                </li>
                <li class="brutal bg-paper p-5">
                    <p class="font-display text-3xl">{{ angka(ringkasan.total_dibaca) }}</p>
                    <p class="mt-1 text-sm font-bold">Total Dibaca</p>
                    <p class="mt-1 text-xs text-muted">Rata-rata {{ angka(ringkasan.rata_dibaca) }} per artikel terbit</p>
                </li>
                <li class="brutal bg-paper p-5">
                    <p class="font-display text-3xl">{{ angka(ringkasan.belum_terjemah) }}</p>
                    <p class="mt-1 text-sm font-bold">Belum Diterjemahkan</p>
                    <Link href="/panel/artikel?terjemahan=belum" class="mt-1 inline-block text-xs font-bold underline">
                        Lihat daftarnya →
                    </Link>
                </li>
            </ul>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Terpopuler -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Artikel Terpopuler</h2>

                    <p v-if="!terpopuler.length" class="mt-3 text-sm text-muted">Belum ada artikel terbit.</p>

                    <ol v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="(baris, i) in terpopuler" :key="baris.id" class="flex items-start gap-3 py-2.5">
                            <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center border-2 border-ink bg-paper-alt text-xs font-bold">{{ i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <Link :href="`/panel/artikel/${baris.id}`" class="text-sm font-bold hover:underline">
                                    {{ baris.judul ?? '(tanpa judul)' }}
                                </Link>
                                <p class="text-xs text-muted">{{ baris.label_tipe }} · {{ baris.penulis }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-bold">{{ angka(baris.dilihat) }}</span>
                        </li>
                    </ol>
                </section>

                <!-- Per penulis -->
                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Produktivitas Penulis</h2>

                    <p v-if="!perPenulis.length" class="mt-3 text-sm text-muted">Belum ada data penulis.</p>

                    <table v-else class="mt-3 w-full text-sm">
                        <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                            <tr>
                                <th class="py-2">Penulis</th>
                                <th class="py-2 text-right">Artikel</th>
                                <th class="py-2 text-right">Terbit</th>
                                <th class="py-2 text-right">Dibaca</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="baris in perPenulis" :key="baris.penulis" class="border-b-2 border-ink/10">
                                <td class="py-2 font-bold">{{ baris.penulis }}</td>
                                <td class="py-2 text-right">{{ angka(baris.jumlah) }}</td>
                                <td class="py-2 text-right">{{ angka(baris.jumlah_terbit) }}</td>
                                <td class="py-2 text-right">{{ angka(baris.total_dibaca) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- Per tipe -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Per Jenis Publikasi</h2>

                <p v-if="!perTipe.length" class="mt-3 text-sm text-muted">Belum ada data.</p>

                <ul v-else class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="baris in perTipe" :key="baris.tipe" class="border-2 border-ink p-3">
                        <p class="font-display text-base">{{ baris.tipe }}</p>
                        <p class="mt-1 text-sm">{{ angka(baris.jumlah) }} artikel</p>
                        <p class="text-xs text-muted">{{ angka(baris.total_dibaca) }} kali dibaca</p>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
