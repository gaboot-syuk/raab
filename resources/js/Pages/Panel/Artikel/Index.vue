<script setup lang="ts">
/*
 * Daftar artikel + alur redaksi.
 * Penulis hanya melihat artikelnya sendiri; pengelola konten melihat semuanya
 * dan mendapat tombol Terbitkan / Minta Revisi / Tolak.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Artikel {
    id: number;
    tipe: string;
    label_tipe: string;
    status: string;
    label_status: string;
    judul_id: string | null;
    judul_en: string | null;
    kategori: string | null;
    penulis: string | null;
    unggulan: boolean;
    dilihat: number;
    kelengkapan_en: number;
    terbit_pada: string | null;
    dijadwalkan_pada: string | null;
    diperbarui_pada: string | null;
}

const props = defineProps<{
    daftar: {
        data: Artikel[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    saring: { status: string; tipe: string; kategori: string; cari: string; terjemahan: string };
    bolehKelola: boolean;
    bolehTerbitkanBeritaAcara: boolean;
    pilihanTipe: Record<string, string>;
    pilihanStatus: Record<string, string>;
    jumlah: Record<string, number>;
    jumlahBelumTerjemah: number;
    daftarKategori: { id: number; nama: string }[];
}>();

const cariLokal = ref(props.saring.cari);

const tab = computed(() => [
    { nilai: 'semua', label: 'Semua', jumlah: props.jumlah.semua },
    { nilai: 'draf', label: 'Draf', jumlah: props.jumlah.draf },
    { nilai: 'menunggu_review', label: 'Menunggu Review', jumlah: props.jumlah.menunggu_review },
    { nilai: 'perlu_revisi', label: 'Perlu Revisi', jumlah: props.jumlah.perlu_revisi },
    { nilai: 'terbit', label: 'Terbit', jumlah: props.jumlah.terbit },
    { nilai: 'ditolak', label: 'Ditolak', jumlah: props.jumlah.ditolak },
]);

function saringUlang(tambahan: Record<string, string>) {
    router.get('/panel/artikel', { ...props.saring, cari: cariLokal.value, ...tambahan }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function aksi(artikel: Artikel, jalur: string, catatan?: string) {
    router.post(`/panel/artikel/${artikel.id}/${jalur}`, catatan ? { catatan } : {}, {
        preserveScroll: true,
    });
}

const modalCatatan = ref<{ artikel: Artikel; jenis: 'revisi' | 'tolak' } | null>(null);
const formCatatan = useForm({ catatan: '' });

function kirimCatatan() {
    if (!modalCatatan.value) {
        return;
    }

    formCatatan.post(`/panel/artikel/${modalCatatan.value.artikel.id}/${modalCatatan.value.jenis}`, {
        preserveScroll: true,
        onSuccess: () => {
            modalCatatan.value = null;
            formCatatan.reset();
        },
    });
}

function hapus(artikel: Artikel) {
    if (!confirm(`Hapus artikel "${artikel.judul_id}"? Naskah dapat dipulihkan oleh Superadmin karena penghapusan bersifat lunak.`)) {
        return;
    }

    router.delete(`/panel/artikel/${artikel.id}`, { preserveScroll: true });
}

function terjadwal(artikel: Artikel): boolean {
    return artikel.status === 'terbit' && artikel.terbit_pada !== null && new Date(artikel.terbit_pada) > new Date();
}
</script>

<template>
    <PanelLayout>
        <Head title="Publikasi" />

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Publikasi</h1>
                    <p class="mt-1 text-sm text-muted">
                        {{ bolehKelola
                            ? 'Kelola seluruh artikel, beri catatan review, dan atur penerbitan.'
                            : 'Karya yang kamu tulis dan status peninjauannya.' }}
                    </p>
                </div>

                <Link href="/panel/artikel/baru" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper">
                    + Artikel Baru
                </Link>
            </div>

            <PesanHasil />

            <!-- Saringan status -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="t in tab"
                    :key="t.nilai"
                    type="button"
                    class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                    :class="saring.status === t.nilai ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                    @click="saringUlang({ status: t.nilai })"
                >
                    {{ t.label }}
                    <span class="ml-1 border-2 border-ink bg-paper px-1 text-[11px]">{{ t.jumlah }}</span>
                </button>
            </div>

            <!-- Saringan lain -->
            <div class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="tipe" class="block text-xs font-bold uppercase text-muted">Tipe</label>
                    <select id="tipe" :value="saring.tipe" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saringUlang({ tipe: ($event.target as HTMLSelectElement).value })">
                        <option value="semua">Semua</option>
                        <option v-for="(label, nilai) in pilihanTipe" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </div>

                <div>
                    <label for="kategori" class="block text-xs font-bold uppercase text-muted">Kategori</label>
                    <select id="kategori" :value="saring.kategori" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saringUlang({ kategori: ($event.target as HTMLSelectElement).value })">
                        <option value="semua">Semua</option>
                        <option v-for="k in daftarKategori" :key="k.id" :value="String(k.id)">{{ k.nama }}</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="brutal-sm px-3 py-2 text-sm font-bold"
                        :class="saring.terjemahan === 'belum' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                        @click="saringUlang({ terjemahan: saring.terjemahan === 'belum' ? '' : 'belum' })"
                    >
                        Belum diterjemahkan ({{ jumlahBelumTerjemah }})
                    </button>
                </div>

                <form class="flex items-end gap-2" @submit.prevent="saringUlang({})">
                    <input v-model="cariLokal" type="search" placeholder="Cari judul…" class="brutal-sm w-full bg-paper-alt px-3 py-2 text-sm">
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-3 py-2 text-sm font-bold text-paper">Cari</button>
                </form>
            </div>

            <!-- Daftar -->
            <p v-if="!daftar.data.length" class="brutal bg-paper-alt px-4 py-10 text-center text-sm text-muted">
                Belum ada artikel pada kategori ini.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="a in daftar.data" :key="a.id" class="brutal bg-paper p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="border-2 border-ink bg-accent-100 px-1.5 py-0.5 text-[11px] font-bold uppercase">{{ a.label_tipe }}</span>
                                <span
                                    class="border-2 border-ink px-1.5 py-0.5 text-[11px] font-bold uppercase"
                                    :class="a.status === 'terbit' ? 'bg-success text-paper' : a.status === 'menunggu_review' ? 'bg-accent-400 text-primary-800' : a.status === 'perlu_revisi' ? 'bg-accent-100' : 'bg-paper-alt text-muted'"
                                >{{ a.label_status }}</span>
                                <span v-if="terjadwal(a)" class="border-2 border-ink bg-accent-400 px-1.5 py-0.5 text-[11px] font-bold uppercase text-primary-800">Terjadwal</span>
                                <span v-if="a.unggulan" class="border-2 border-ink bg-accent-400 px-1.5 py-0.5 text-[11px] font-bold uppercase text-primary-800">Unggulan</span>
                            </div>

                            <h2 class="mt-2 font-display text-lg">{{ a.judul_id ?? '(tanpa judul)' }}</h2>
                            <p class="text-sm text-muted">{{ a.judul_en || 'Versi Inggris belum diisi' }}</p>

                            <p class="mt-2 text-xs text-muted">
                                {{ a.kategori || 'Tanpa kategori' }} ·
                                {{ a.penulis }} ·
                                Diperbarui {{ a.diperbarui_pada }}
                                <span v-if="a.terbit_pada"> · Terbit {{ a.terbit_pada }}</span>
                                · {{ a.dilihat }}x dibaca
                            </p>
                        </div>

                        <div class="w-28 shrink-0">
                            <p class="text-[11px] font-bold uppercase text-muted">Terjemahan</p>
                            <div class="mt-1 h-3 border-2 border-ink bg-paper-alt">
                                <div class="h-full bg-accent-400" :style="{ width: a.kelengkapan_en + '%' }"></div>
                            </div>
                            <p class="mt-1 text-[11px] font-bold">{{ a.kelengkapan_en }}%</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1 border-t-2 border-ink/15 pt-3">
                        <Link :href="`/panel/artikel/${a.id}`" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold">Sunting</Link>

                        <button
                            v-if="(a.status === 'draf' || a.status === 'perlu_revisi') && a.tipe !== 'berita_acara'"
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-400 px-2 py-1 text-xs font-bold text-primary-800"
                            @click="aksi(a, 'kirim')"
                        >Kirim untuk Review</button>

                        <!-- Berita acara: diterbitkan langsung oleh Sekretaris, tanpa review -->
                        <button
                            v-if="bolehTerbitkanBeritaAcara && a.tipe === 'berita_acara' && a.status !== 'terbit'"
                            type="button"
                            class="brutal-sm brutal-hover bg-primary-600 px-2 py-1 text-xs font-bold text-paper"
                            @click="aksi(a, 'terbitkan-berita-acara')"
                        >Terbitkan Berita Acara</button>

                        <template v-if="bolehKelola">
                            <button
                                v-if="a.tipe !== 'berita_acara' && (a.status === 'menunggu_review' || a.status === 'draf' || a.status === 'perlu_revisi')"
                                type="button"
                                class="brutal-sm brutal-hover bg-primary-600 px-2 py-1 text-xs font-bold text-paper"
                                @click="aksi(a, 'terbitkan')"
                            >{{ a.dijadwalkan_pada ? 'Terbitkan (terjadwal)' : 'Terbitkan' }}</button>

                            <button
                                v-if="a.status === 'menunggu_review'"
                                type="button"
                                class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold"
                                @click="modalCatatan = { artikel: a, jenis: 'revisi' }"
                            >Minta Revisi</button>

                            <button
                                v-if="a.status === 'menunggu_review'"
                                type="button"
                                class="brutal-sm brutal-hover bg-accent-100 px-2 py-1 text-xs font-bold"
                                @click="modalCatatan = { artikel: a, jenis: 'tolak' }"
                            >Tolak</button>

                            <button
                                v-if="a.status === 'terbit'"
                                type="button"
                                class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold"
                                @click="aksi(a, 'unggulan')"
                            >{{ a.unggulan ? 'Lepas Unggulan' : 'Jadikan Unggulan' }}</button>

                            <button
                                v-if="a.status === 'terbit'"
                                type="button"
                                class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold"
                                @click="aksi(a, 'tarik')"
                            >Tarik jadi Draf</button>
                        </template>

                        <button type="button" class="brutal-sm brutal-hover ml-auto bg-accent-100 px-2 py-1 text-xs font-bold" @click="hapus(a)">Hapus</button>
                    </div>

                    <p v-if="a.status === 'perlu_revisi'" class="mt-2 text-xs font-semibold text-muted">
                        Menunggu perbaikan dari penulis.
                    </p>
                </li>
            </ul>

            <Penomoran :tautan="daftar.links" />
        </div>

        <!-- Modal catatan review -->
        <div v-if="modalCatatan" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="modalCatatan = null">
            <form class="brutal w-full max-w-lg bg-paper p-5" @submit.prevent="kirimCatatan">
                <h2 class="font-display text-lg">
                    {{ modalCatatan.jenis === 'revisi' ? 'Minta Revisi' : 'Tolak Artikel' }}
                </h2>
                <p class="mt-1 text-sm text-muted">{{ modalCatatan.artikel.judul_id }}</p>
                <p class="mt-2 text-sm">
                    {{ modalCatatan.jenis === 'revisi'
                        ? 'Sebutkan bagian yang perlu diperbaiki — penulis akan menerimanya lewat email.'
                        : 'Alasan penolakan wajib jelas agar penulis dapat belajar darinya.' }}
                </p>

                <label for="catatan-artikel" class="mt-4 block text-sm font-bold">Catatan *</label>
                <textarea id="catatan-artikel" v-model="formCatatan.catatan" rows="5" required minlength="10" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                <p class="mt-1 text-xs text-muted">Minimal 10 karakter.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalCatatan = null">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formCatatan.processing">
                        Kirim
                    </button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
