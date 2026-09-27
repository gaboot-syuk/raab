<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage<{
    auth: {
        user: { id: number; name: string; email: string } | null;
        roles: string[];
        permissions: string[];
    };
    panel: { pesan_baru: number; notifikasi_belum_dibaca: number };
    locale: string;
}>();

const pengguna = computed(() => page.props.auth?.user);
const peran = computed<string[]>(() => page.props.auth?.roles ?? []);
const izin = computed<string[]>(() => page.props.auth?.permissions ?? []);
const pesanBaru = computed(() => page.props.panel?.pesan_baru ?? 0);
const notifikasiBaru = computed(() => page.props.panel?.notifikasi_belum_dibaca ?? 0);

/**
 * Lencana pada sebuah menu. Pesan masuk dan notifikasi punya sumber angka yang
 * berbeda, jadi angkanya diambil lewat nama kunci yang ditulis di menu — bukan
 * dengan menebak dari tautannya di dua tempat.
 */
function lencana(item: (typeof menu)[number]): number {
    if (item.lencana === 'notifikasi_belum_dibaca') return notifikasiBaru.value;

    return 0;
}

/**
 * Menu panel. `izin` menentukan siapa yang boleh melihat menunya, sehingga
 * setiap peran hanya melihat yang menjadi haknya. `fase` menandai menu yang
 * modulnya belum dibangun.
 */
const menu = [
    { label: 'Dasbor', ikon: '▦', tautan: '/panel', izin: null, fase: null },
    { label: 'Pesan Masuk', ikon: '✉', tautan: '/panel/pesan', izin: 'messages.view', fase: null },
    { label: 'Halaman Statis', ikon: '◫', tautan: '/panel/halaman', izin: 'pages.manage', fase: null },
    { label: 'Slider Beranda', ikon: '❐', tautan: '/panel/slider', izin: 'sliders.manage', fase: null },
    { label: 'Pustaka Media', ikon: '▣', tautan: '/panel/media', izin: 'media.view', fase: null },
    { label: 'Pengaturan Situs', ikon: '⚙', tautan: '/panel/pengaturan', izin: 'settings.site-manage', fase: null },
    { label: 'Akun Pengurus', ikon: '☰', tautan: '/panel/pengguna', izin: 'users.view', fase: null },
    { label: 'Peran & Izin', ikon: '⚿', tautan: '/panel/peran', izin: 'roles.manage', fase: null },
    { label: 'Verifikasi Anggota', ikon: '✓', tautan: '/panel/verifikasi', izin: 'verifications.view', fase: null },
    { label: 'Keanggotaan', ikon: '👥', tautan: '/panel/keanggotaan', izin: 'members.view', fase: null },
    { label: 'Publikasi', ikon: '✎', tautan: '/panel/artikel', izin: 'articles.view', fase: null },
    { label: 'Kategori & Tag', ikon: '⌸', tautan: '/panel/kategori-artikel', izin: 'article-categories.manage', fase: null },
    { label: 'Statistik Publikasi', ikon: '📊', tautan: '/panel/statistik-publikasi', izin: 'articles.view', fase: null },
    { label: 'Periode Kepengurusan', ikon: '⌘', tautan: '/panel/organisasi/periode', izin: 'periods.view', fase: null },
    { label: 'Jabatan', ikon: '⌸', tautan: '/panel/organisasi/jabatan', izin: 'positions.view', fase: null },
    { label: 'Penugasan Pengurus', ikon: '☰', tautan: '/panel/organisasi/penugasan', izin: 'assignments.manage', fase: null },
    { label: 'Biro & LSO', ikon: '⬢', tautan: '/panel/organisasi/unit', izin: 'units.view', fase: null },
    { label: 'Galeri & Agenda Unit', ikon: '❐', tautan: '/panel/organisasi/galeri', izin: 'galleries.manage', fase: null },
    { label: 'Daftar Mentor', ikon: '◈', tautan: '/panel/organisasi/mentor', izin: 'mentors.view', fase: null },
    { label: 'Event Mapaba & PKD', ikon: '◇', tautan: '/panel/event', izin: 'events.view', fase: null },
    { label: 'Peserta Event', ikon: '▤', tautan: '/panel/peserta', izin: 'registrations.view', fase: null },
    { label: 'Kegiatan', ikon: '◷', tautan: '/panel/kegiatan', izin: 'activities.view', fase: null },
    { label: 'Presensi', ikon: '✓', tautan: '/panel/presensi', izin: 'attendances.view', fase: null },
    { label: 'Poin Kontribusi', ikon: '★', tautan: '/panel/kontribusi', izin: 'points.view', fase: null },
    { label: 'Prestasi Kader', ikon: '◈', tautan: '/panel/prestasi', izin: 'achievements.view', fase: null },
    { label: 'Aspirasi', ikon: '✉', tautan: '/panel/aspirasi', izin: 'aspirations.view', fase: null },
    { label: 'Pengumuman', ikon: '❢', tautan: '/panel/pengumuman', izin: 'announcements.view', fase: null },
    { label: 'Arsip Dokumen', ikon: '❑', tautan: '/panel/arsip', izin: 'documents.view', fase: null },
    { label: 'Laporan', ikon: '📈', tautan: '/panel/laporan', izin: 'reports.generate', fase: null },
    { label: 'Cadangan Basis Data', ikon: '🗄', tautan: '/panel/cadangan', izin: null, fase: null, peran: 'superadmin' },
    { label: 'Diagnostik', ikon: '🩺', tautan: '/panel/diagnostik', izin: null, fase: null, peran: 'superadmin' },
    { label: 'Notifikasi', ikon: '🔔', tautan: '/notifikasi', izin: null, fase: null, lencana: 'notifikasi_belum_dibaca' },
    { label: 'Inventaris', ikon: '▦', tautan: '/panel/inventaris', izin: 'inventory.items.view', fase: null },
    { label: 'Katalog Buku', ikon: '▥', tautan: '/panel/perpustakaan', izin: 'library.books.view', fase: null },
    { label: 'Peminjaman', ikon: '⇄', tautan: '/panel/peminjaman', izin: 'loans.view', fase: null },
    { label: 'Keuangan', ikon: 'Rp', tautan: '/panel/keuangan', izin: 'finance.transactions.view', fase: null },
    { label: 'Buku Kas', ikon: '❐', tautan: '/panel/keuangan/transaksi', izin: 'finance.transactions.view', fase: null },
    { label: 'Iuran', ikon: '◫', tautan: '/panel/keuangan/iuran', izin: 'dues.manage', fase: null },
    { label: 'Anggaran (RKAT)', ikon: '▨', tautan: '/panel/keuangan/anggaran', izin: 'budgets.manage', fase: null },
    { label: 'Hibah & Dukungan', ikon: '★', tautan: '/panel/keuangan/hibah', izin: 'donations.view', fase: null },
    { label: 'Laporan Keuangan', ikon: '📈', tautan: '/panel/keuangan/laporan', izin: 'finance.reports.view', fase: null },
];

/** Menu yang boleh dilihat pengguna saat ini. */
const menuTampil = computed(() =>
    menu.filter((item) => {
        // Sebagian menu dibatasi PERAN, bukan izin — khususnya menu yang
        // menyentuh seluruh isi basis data. Keduanya diperiksa di tempat yang
        // sama supaya tidak ada menu yang lolos karena hanya satu cara
        // penyaringan yang diperiksa.
        if (item.peran && !peran.value.includes(item.peran)) {
            return false;
        }

        return item.izin === null || izin.value.includes(item.izin);
    }),
);

/** Tandai menu yang sedang dibuka. */
function sedangAktif(tautan: string | null): boolean {
    if (!tautan) {
        return false;
    }

    const jalur = page.url.split('?')[0];

    return tautan === '/panel' ? jalur === '/panel' : jalur.startsWith(tautan);
}

/* --- Mode gelap: terang / gelap / ikut sistem --- */
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
    const tersimpan = localStorage.getItem('tema') as 'terang' | 'gelap' | 'sistem' | null;
    tema.value = tersimpan ?? 'sistem';
    terapkanTema();
});

const laci = ref(false);
</script>

<template>
    <div class="flex min-h-screen bg-paper-alt text-ink">
        <!-- ===== Sidebar (desktop) ===== -->
        <aside class="hidden w-72 shrink-0 border-r-2 border-ink bg-paper lg:block">
            <div class="flex items-center gap-3 border-b-2 border-ink bg-brand px-4 py-4 text-on-brand">
                <img
                    src="/brand/logo-pmii-raab.png"
                    alt="Logo PMII RAAB"
                    class="h-10 w-10 border-2 border-ink bg-on-brand object-contain p-0.5"
                >
                <div class="min-w-0">
                    <p class="truncate font-display text-sm">Panel Pengurus</p>
                    <p class="truncate text-[11px] text-on-brand/75">PMII RAAB</p>
                </div>
            </div>

            <nav class="p-3">
                <ul class="space-y-1">
                    <li v-for="item in menuTampil" :key="item.label">
                        <a
                            v-if="item.tautan"
                            :href="item.tautan"
                            class="flex items-center gap-3 border-2 border-ink px-3 py-2 text-sm font-bold"
                            :class="sedangAktif(item.tautan) ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                        >
                            <span aria-hidden="true">{{ item.ikon }}</span>
                            <span class="flex-1">{{ item.label }}</span>
                            <span
                                v-if="lencana(item) > 0"
                                class="border-2 border-ink bg-primary-600 px-1.5 text-[10px] font-bold text-paper"
                                :title="`${lencana(item)} belum dibaca`"
                            >{{ lencana(item) }}</span>
                            <span
                                v-else-if="item.tautan === '/panel/pesan' && pesanBaru > 0"
                                class="border-2 border-ink bg-primary-600 px-1.5 text-[10px] font-bold text-paper"
                                :title="`${pesanBaru} pesan belum dibaca`"
                            >{{ pesanBaru }}</span>
                        </a>
                        <span
                            v-else
                            class="flex items-center justify-between gap-2 border-2 border-dashed border-ink/25 px-3 py-2 text-sm font-semibold text-muted"
                            :title="`Tersedia pada ${item.fase}`"
                        >
                            <span class="flex items-center gap-3">
                                <span aria-hidden="true">{{ item.ikon }}</span>
                                {{ item.label }}
                            </span>
                            <span class="border-2 border-ink bg-paper-alt px-1 text-[10px] font-bold uppercase">
                                {{ item.fase?.replace('Fase ', 'F') }}
                            </span>
                        </span>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- ===== Isi ===== -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 border-b-2 border-ink bg-paper">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="border-2 border-ink bg-paper p-2 lg:hidden"
                            aria-label="Buka menu"
                            @click="laci = true"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="square" />
                            </svg>
                        </button>

                        <div>
                            <p class="font-display text-base leading-tight">{{ pengguna?.name }}</p>
                            <p class="text-xs text-muted">{{ pengguna?.email }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            v-for="p in peran"
                            :key="p"
                            class="hidden border-2 border-ink bg-accent-400 px-2 py-0.5 text-[11px] font-bold uppercase text-primary-800 sm:inline-block"
                        >
                            {{ p }}
                        </span>

                        <!-- Pengalih tema -->
                        <div class="flex items-center border-2 border-ink bg-paper">
                            <button
                                type="button"
                                class="px-2 py-1 text-xs font-bold uppercase"
                                :class="tema === 'terang' ? 'bg-accent-400 text-primary-800' : ''"
                                @click="pilihTema('terang')"
                            >
                                A
                            </button>
                            <button
                                type="button"
                                class="border-l-2 border-ink px-2 py-1 text-xs font-bold uppercase"
                                :class="tema === 'gelap' ? 'bg-accent-400 text-primary-800' : ''"
                                @click="pilihTema('gelap')"
                            >
                                G
                            </button>
                            <button
                                type="button"
                                class="border-l-2 border-ink px-2 py-1 text-xs font-bold uppercase"
                                :class="tema === 'sistem' ? 'bg-accent-400 text-primary-800' : ''"
                                @click="pilihTema('sistem')"
                            >
                                S
                            </button>
                        </div>

                        <a
                            href="/"
                            class="hidden border-2 border-ink bg-paper px-3 py-2 text-xs font-bold sm:block"
                        >Lihat Situs</a>

                        <a
                            href="/dasbor"
                            class="hidden border-2 border-ink bg-paper px-3 py-2 text-xs font-bold sm:block"
                        >Area Anggota</a>

                        <form method="POST" action="/logout" class="inline-flex">
                            <input type="hidden" name="_token" :value="$page.props.csrf_token">
                            <button
                                type="submit"
                                class="border-2 border-ink bg-danger px-3 py-2 text-xs font-bold text-paper"
                            >Keluar</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                <slot />
            </main>
        </div>

        <!-- ===== Laci menu (mobile) ===== -->
        <div v-if="laci" class="fixed inset-0 z-40 bg-ink/60 lg:hidden" @click="laci = false" aria-hidden="true"></div>
        <aside
            v-if="laci"
            class="fixed inset-y-0 left-0 z-50 w-[85%] max-w-xs overflow-y-auto border-r-2 border-ink bg-paper lg:hidden"
        >
            <div class="flex items-center justify-between border-b-2 border-ink bg-brand px-4 py-3 text-on-brand">
                <span class="font-display text-sm">Menu Panel</span>
                <button type="button" class="border-2 border-ink bg-paper px-2 text-primary-800" @click="laci = false">
                    ✕
                </button>
            </div>
            <ul class="divide-y-2 divide-ink/10">
                <li v-for="item in menuTampil" :key="item.label">
                    <a
                        v-if="item.tautan"
                        :href="item.tautan"
                        class="flex items-center justify-between px-4 py-3 text-sm font-bold"
                        :class="sedangAktif(item.tautan) ? 'bg-accent-400 text-primary-800' : 'bg-paper'"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            v-if="lencana(item) > 0"
                            class="border-2 border-ink bg-primary-600 px-1.5 text-[10px] text-paper"
                        >{{ lencana(item) }}</span>
                        <span
                            v-else-if="item.tautan === '/panel/pesan' && pesanBaru > 0"
                            class="border-2 border-ink bg-primary-600 px-1.5 text-[10px] text-paper"
                        >{{ pesanBaru }}</span>
                    </a>
                    <span v-else class="flex items-center justify-between px-4 py-3 text-sm font-semibold text-muted">
                        {{ item.label }}
                        <span class="border-2 border-ink bg-paper-alt px-1 text-[10px] font-bold uppercase">{{ item.fase }}</span>
                    </span>
                </li>
            </ul>
        </aside>
    </div>
</template>
