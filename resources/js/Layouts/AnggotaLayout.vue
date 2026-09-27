<script setup lang="ts">
/*
 * Kerangka area anggota (kader & alumni).
 *
 * Dipisahkan dari PanelLayout karena area ini tidak memerlukan izin khusus dan
 * menunya jauh lebih ringkas — hanya dasbor, profil, dan tautan ke panel
 * (bila pengguna memang memegang peran pengurus).
 */
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage<{
    auth: { user: { name: string; email: string } | null; roles: string[]; permissions: string[] };
    panel: { notifikasi_belum_dibaca: number };
}>();

const notifikasiBaru = computed(() => page.props.panel?.notifikasi_belum_dibaca ?? 0);

const pengguna = computed(() => page.props.auth?.user);
const adalahPengurus = computed(() => (page.props.auth?.roles ?? []).length > 0);

const menu = computed(() => [
    { label: 'Dasbor', tautan: '/dasbor' },
    { label: 'Kartu', tautan: '/kartu-kader' },
    { label: 'Kegiatan', tautan: '/kegiatan' },
    { label: 'Poin', tautan: '/kontribusi' },
    { label: 'Prestasi', tautan: '/prestasi-saya' },
    { label: 'Pengumuman', tautan: '/pengumuman-internal' },
    { label: 'Arsip', tautan: '/arsip-internal' },
    { label: 'Notifikasi', tautan: '/notifikasi' },
    { label: 'Pustaka', tautan: '/pustaka' },
    { label: 'Iuran', tautan: '/iuran' },
    { label: 'Hibah', tautan: '/hibah' },
    { label: 'Karya Saya', tautan: '/karya' },
    { label: 'Profil Saya', tautan: '/profil' },
]);

function sedangAktif(tautan: string): boolean {
    const jalur = page.url.split('?')[0];

    return tautan === '/dasbor' ? jalur === '/dasbor' : jalur.startsWith(tautan);
}

/* Mode gelap: terang / gelap / ikut sistem */
const tema = ref<'terang' | 'gelap' | 'sistem'>('sistem');

function terapkanTema() {
    const gelap =
        tema.value === 'gelap' ||
        (tema.value === 'sistem' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', gelap);
    localStorage.setItem('tema', tema.value);
}

function pilihTema(nilai: 'terang' | 'gelap' | 'sistem') {
    tema.value = nilai;
    terapkanTema();
}

onMounted(() => {
    tema.value = (localStorage.getItem('tema') as typeof tema.value) ?? 'sistem';
    terapkanTema();
});
</script>

<template>
    <div class="min-h-screen bg-paper-alt text-ink">
        <header class="border-b-2 border-ink bg-brand text-on-brand">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <a href="/dasbor" class="flex min-w-0 items-center gap-3">
                    <img
                        src="/brand/logo-pmii-raab.png"
                        alt="Logo PMII RAAB"
                        class="h-10 w-10 shrink-0 border-2 border-ink bg-on-brand object-contain p-0.5"
                    >
                    <span class="min-w-0">
                        <span class="block truncate font-display text-sm leading-tight">Area Anggota</span>
                        <span class="block truncate text-[11px] leading-tight text-on-brand/75">PMII RAAB</span>
                    </span>
                </a>

                <div class="flex items-center gap-2">
                    <div class="flex items-center border-2 border-ink bg-paper">
                        <button
                            v-for="nilai in (['terang', 'gelap', 'sistem'] as const)"
                            :key="nilai"
                            type="button"
                            class="border-ink px-2 py-1 text-xs font-bold uppercase text-primary-800 first:border-0 border-l-2"
                            :class="tema === nilai ? 'bg-accent-400' : 'bg-paper'"
                            :title="`Tema ${nilai}`"
                            @click="pilihTema(nilai)"
                        >{{ nilai.charAt(0).toUpperCase() }}</button>
                    </div>

                    <a v-if="adalahPengurus" href="/panel" class="hidden border-2 border-ink bg-accent-400 px-3 py-2 text-xs font-bold text-primary-800 sm:block">
                        Panel Pengurus
                    </a>

                    <form method="POST" action="/logout" class="inline-flex">
                        <input type="hidden" name="_token" :value="$page.props.csrf_token">
                        <button type="submit" class="border-2 border-ink bg-danger px-3 py-2 text-xs font-bold text-paper">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>

            <nav class="mx-auto max-w-5xl px-4 pb-3" aria-label="Menu anggota">
                <ul class="flex flex-wrap gap-2">
                    <li v-for="item in menu" :key="item.tautan">
                        <a
                            :href="item.tautan"
                            class="inline-block border-2 border-ink px-3 py-1.5 text-sm font-bold"
                            :class="sedangAktif(item.tautan) ? 'bg-accent-400 text-primary-800' : 'bg-on-brand text-primary-800'"
                        >{{ item.label }}<span
                            v-if="item.tautan === '/notifikasi' && notifikasiBaru > 0"
                            class="ml-2 inline-block border-2 border-ink bg-primary-600 px-1.5 text-[10px] text-paper"
                            :title="`${notifikasiBaru} belum dibaca`"
                        >{{ notifikasiBaru }}</span></a>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            <p v-if="pengguna" class="mb-6 text-sm text-muted">
                Masuk sebagai <span class="font-bold text-ink">{{ pengguna.name }}</span> ({{ pengguna.email }})
            </p>
            <slot />
        </main>

        <footer class="border-t-2 border-ink py-4 text-center text-xs text-muted">
            <a href="/" class="font-bold underline">Kembali ke situs</a>
        </footer>
    </div>
</template>
