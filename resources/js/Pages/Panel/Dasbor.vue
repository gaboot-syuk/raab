<script setup lang="ts">
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    ringkasan: { label: string; nilai: number; tautan: string }[];
    pintasan: { label: string; tautan: string }[];
    peran: string[];
    izin: string[];
    aktivitas: { oleh: string; keterangan: string; waktu: string }[];
}>();

const labelPeran: Record<string, string> = {
    superadmin: 'Superadmin',
    sekretaris: 'Sekretaris',
    bendahara: 'Bendahara',
    konten_manager: 'Konten Manager',
};
</script>

<template>
    <PanelLayout>
        <Head title="Dasbor" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Dasbor</h1>
                <p class="mt-1 text-sm text-muted">
                    Selamat datang di panel pengurus PMII Rayon Ali Ahmad Baktsir.
                </p>
            </div>

            <!-- Kartu ringkasan (hanya modul yang menjadi hak pengguna) -->
            <ul v-if="ringkasan.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="kartu in ringkasan" :key="kartu.label">
                    <Link :href="kartu.tautan" class="brutal brutal-hover block h-full bg-paper p-5">
                        <p class="font-display text-3xl">{{ kartu.nilai }}</p>
                        <p class="mt-1 text-sm font-bold">{{ kartu.label }}</p>
                        <p class="mt-2 border-t-2 border-ink pt-2 text-[11px] font-bold uppercase text-muted">
                            Buka →
                        </p>
                    </Link>
                </li>
            </ul>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Pintasan kerja -->
                <div class="brutal bg-accent-100 p-5 lg:col-span-2">
                    <h2 class="font-display text-lg">Pintasan Kerja</h2>

                    <p v-if="!pintasan.length" class="mt-3 text-sm">
                        Belum ada menu yang tersedia untuk peranmu. Hubungi Superadmin.
                    </p>

                    <ul v-else class="mt-3 grid gap-2 sm:grid-cols-2">
                        <li v-for="p in pintasan" :key="p.tautan">
                            <Link
                                :href="p.tautan"
                                class="brutal-sm brutal-hover block bg-paper px-3 py-2 text-sm font-bold"
                            >{{ p.label }}</Link>
                        </li>
                    </ul>
                </div>

                <!-- Hak akses -->
                <div class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Hak Akses Anda</h2>

                    <div class="mt-3 flex flex-wrap gap-1">
                        <span
                            v-for="p in peran"
                            :key="p"
                            class="border-2 border-ink bg-accent-400 px-1.5 py-0.5 text-[11px] font-bold uppercase text-primary-800"
                        >{{ labelPeran[p] ?? p }}</span>
                    </div>

                    <p class="mt-3 text-sm text-muted">
                        Jumlah izin: <span class="font-bold text-ink">{{ izin.length }}</span>
                    </p>

                    <Link href="/panel/peran" class="brutal-sm brutal-hover mt-4 inline-block bg-paper px-3 py-2 text-xs font-bold">
                        Lihat rincian peran
                    </Link>
                </div>
            </div>

            <!-- Jejak audit (Superadmin) -->
            <div v-if="aktivitas.length" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Aktivitas Terakhir</h2>

                <ul class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="(baris, i) in aktivitas" :key="i" class="flex flex-wrap items-baseline justify-between gap-2 py-2 text-sm">
                        <span>
                            <span class="font-bold">{{ baris.oleh }}</span>
                            — {{ baris.keterangan }}
                        </span>
                        <span class="text-xs text-muted">{{ baris.waktu }}</span>
                    </li>
                </ul>
            </div>

            <div class="brutal bg-paper-alt p-5">
                <h2 class="font-display text-lg">Tentang Halaman Ini</h2>
                <p class="mt-2 text-sm text-muted">
                    Kartu angka dan pintasan di atas hanya menampilkan modul yang menjadi hakmu. Kalau ada modul yang
                    kamu butuhkan tetapi tidak muncul, berarti izinnya belum diberikan pada peranmu — hubungi
                    Superadmin, sebutkan modul dan keperluannya.
                </p>
            </div>
        </div>
    </PanelLayout>
</template>
