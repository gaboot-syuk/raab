<script setup lang="ts">
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{
    halaman: {
        id: number;
        kunci: string | null;
        tipe: string;
        judul_id: string | null;
        judul_en: string | null;
        status: string;
        kelengkapan_en: number;
        terbit_pada: string | null;
        diperbarui_pada: string | null;
    }[];
}>();

const page = usePage<{ flash: { sukses?: string; galat?: string } }>();

const labelTipe: Record<string, string> = {
    sejarah: 'Sejarah',
    visi_misi: 'Visi & Misi',
    sambutan: 'Sambutan',
    statis: 'Halaman Bebas',
};
</script>

<template>
    <PanelLayout>
        <Head title="Halaman Statis" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Halaman Statis</h1>
                    <p class="mt-1 text-sm text-muted">
                        Kelola isi Sejarah, Visi &amp; Misi, dan Sambutan. Satu formulir untuk dua bahasa.
                    </p>
                </div>
            </div>

            <!-- Pesan hasil simpan -->
            <p
                v-if="page.props.flash?.sukses"
                class="brutal-sm border-success bg-success/10 px-4 py-3 text-sm font-semibold"
            >
                {{ page.props.flash.sukses }}
            </p>

            <ul class="space-y-3">
                <li
                    v-for="baris in halaman"
                    :key="baris.id"
                    class="brutal bg-paper p-4 sm:flex sm:items-center sm:justify-between sm:gap-6"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="border-2 border-ink bg-accent-100 px-2 py-0.5 text-[11px] font-bold uppercase">
                                {{ labelTipe[baris.tipe] ?? baris.tipe }}
                            </span>
                            <span
                                class="border-2 border-ink px-2 py-0.5 text-[11px] font-bold uppercase"
                                :class="baris.status === 'terbit' ? 'bg-success text-paper' : 'bg-paper-alt text-muted'"
                            >
                                {{ baris.status === 'terbit' ? 'Terbit' : 'Draf' }}
                            </span>
                        </div>

                        <h2 class="mt-2 font-display text-lg">{{ baris.judul_id ?? '(tanpa judul)' }}</h2>
                        <p class="text-sm text-muted">{{ baris.judul_en || 'Versi Inggris belum diisi' }}</p>

                        <p class="mt-2 text-xs text-muted">
                            Diperbarui: {{ baris.diperbarui_pada ?? '—' }}
                            <span v-if="baris.terbit_pada">· Terbit: {{ baris.terbit_pada }}</span>
                        </p>
                    </div>

                    <div class="mt-4 flex items-center gap-4 sm:mt-0 sm:shrink-0">
                        <!-- Kelengkapan terjemahan -->
                        <div class="w-28">
                            <p class="text-[11px] font-bold uppercase text-muted">Terjemahan</p>
                            <div class="mt-1 h-3 border-2 border-ink bg-paper-alt">
                                <div
                                    class="h-full bg-accent-400"
                                    :style="{ width: baris.kelengkapan_en + '%' }"
                                ></div>
                            </div>
                            <p class="mt-1 text-[11px] font-bold">{{ baris.kelengkapan_en }}%</p>
                        </div>

                        <Link
                            :href="`/panel/halaman/${baris.id}`"
                            class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper"
                        >Sunting</Link>
                    </div>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
