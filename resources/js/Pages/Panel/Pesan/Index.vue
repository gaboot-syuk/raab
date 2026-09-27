<script setup lang="ts">
/*
 * Kotak masuk pesan dari formulir "Kontak Rayon".
 * Daftar di kiri, detail di kanan. Membuka pesan otomatis menandainya "dibaca".
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Pesan {
    id: number;
    nama: string;
    email: string;
    telepon: string | null;
    asal: string | null;
    jenis: string;
    label_jenis: string;
    subjek: string;
    pesan: string;
    status: string;
    label_status: string;
    catatan_internal: string | null;
    dibuat_pada: string;
    dibalas_pada: string | null;
}

const props = defineProps<{
    daftar: {
        data: Pesan[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    saring: string;
    cari: string;
    jumlah: { semua: number; baru: number; dibalas: number; arsip: number };
    pilihanStatus: Record<string, string>;
}>();

const terpilihId = ref<number | null>(props.daftar.data[0]?.id ?? null);
const catatan = ref<string>('');
const cariLokal = ref<string>(props.cari);

const terpilih = computed<Pesan | null>(
    () => props.daftar.data.find((p) => p.id === terpilihId.value) ?? null,
);

const tab = computed(() => [
    { nilai: 'semua', label: 'Semua', jumlah: props.jumlah.semua },
    { nilai: 'baru', label: 'Belum Dibaca', jumlah: props.jumlah.baru },
    { nilai: 'dibalas', label: 'Sudah Dibalas', jumlah: props.jumlah.dibalas },
    { nilai: 'arsip', label: 'Arsip', jumlah: props.jumlah.arsip },
]);

/** Pilih pesan; tandai "dibaca" bila sebelumnya masih baru. */
function pilih(pesan: Pesan) {
    terpilihId.value = pesan.id;
    catatan.value = pesan.catatan_internal ?? '';

    if (pesan.status === 'baru') {
        router.patch(
            `/panel/pesan/${pesan.id}`,
            { status: 'dibaca', catatan_internal: pesan.catatan_internal },
            { preserveScroll: true, preserveState: true },
        );
    }
}

function saringKe(nilai: string) {
    router.get('/panel/pesan', nilai === 'semua' ? {} : { status: nilai }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function cari() {
    router.get('/panel/pesan', { cari: cariLokal.value, status: props.saring }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function ubahStatus(pesan: Pesan, status: string) {
    router.patch(`/panel/pesan/${pesan.id}`, { status, catatan_internal: catatan.value }, {
        preserveScroll: true,
    });
}

function simpanCatatan(pesan: Pesan) {
    router.patch(`/panel/pesan/${pesan.id}`, {
        status: pesan.status,
        catatan_internal: catatan.value,
    }, { preserveScroll: true });
}

function hapus(pesan: Pesan) {
    if (!confirm(`Hapus pesan dari ${pesan.nama}? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    router.delete(`/panel/pesan/${pesan.id}`, { preserveScroll: true });
}

// Bila daftar berubah halaman/filter, pilih baris pertama yang tersedia.
watch(
    () => props.daftar.data,
    (baru) => {
        if (!baru.some((p) => p.id === terpilihId.value)) {
            terpilihId.value = baru[0]?.id ?? null;
            catatan.value = baru[0]?.catatan_internal ?? '';
        }
    },
);
</script>

<template>
    <PanelLayout>
        <Head title="Pesan Masuk" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Pesan Masuk</h1>
                <p class="mt-1 text-sm text-muted">
                    Pesan dari formulir Kontak Rayon di situs publik.
                </p>
            </div>

            <PesanHasil />

            <!-- Saringan & pencarian -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="t in tab"
                    :key="t.nilai"
                    type="button"
                    class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                    :class="saring === t.nilai ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                    @click="saringKe(t.nilai)"
                >
                    {{ t.label }}
                    <span class="ml-1 border-2 border-ink bg-paper px-1 text-[11px]">{{ t.jumlah }}</span>
                </button>

                <form class="ml-auto flex gap-2" @submit.prevent="cari">
                    <input
                        v-model="cariLokal"
                        type="search"
                        placeholder="Cari nama, email, subjek…"
                        class="brutal-sm w-56 bg-paper px-3 py-1.5 text-sm"
                    >
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-3 py-1.5 text-sm font-bold text-paper">
                        Cari
                    </button>
                </form>
            </div>

            <div v-if="!daftar.data.length" class="brutal bg-paper-alt px-4 py-10 text-center text-sm text-muted">
                Belum ada pesan pada kategori ini.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-[22rem_1fr]">
                <!-- ===== Daftar ===== -->
                <div class="space-y-2">
                    <button
                        v-for="pesan in daftar.data"
                        :key="pesan.id"
                        type="button"
                        class="w-full border-2 border-ink p-3 text-left"
                        :class="terpilihId === pesan.id ? 'bg-accent-400' : 'bg-paper hover:bg-accent-100'"
                        @click="pilih(pesan)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-bold">{{ pesan.nama }}</span>
                            <span
                                v-if="pesan.status === 'baru'"
                                class="shrink-0 border-2 border-ink bg-primary-600 px-1.5 text-[10px] font-bold uppercase text-paper"
                            >Baru</span>
                        </div>
                        <p class="mt-1 truncate text-sm">{{ pesan.subjek }}</p>
                        <p class="mt-1 text-xs text-muted">{{ pesan.dibuat_pada }} · {{ pesan.label_jenis }}</p>
                    </button>

                    <Penomoran :tautan="daftar.links" />
                </div>

                <!-- ===== Detail ===== -->
                <div v-if="terpilih" class="brutal h-fit bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-ink pb-4">
                        <div class="min-w-0">
                            <h2 class="font-display text-lg">{{ terpilih.subjek }}</h2>
                            <p class="mt-1 text-sm">
                                <span class="font-bold">{{ terpilih.nama }}</span>
                                <span class="text-muted"> · {{ terpilih.email }}</span>
                            </p>
                            <p v-if="terpilih.telepon" class="text-sm text-muted">{{ terpilih.telepon }}</p>
                            <p v-if="terpilih.asal" class="text-sm text-muted">{{ terpilih.asal }}</p>
                        </div>
                        <a
                            :href="`mailto:${terpilih.email}?subject=${encodeURIComponent('Re: ' + terpilih.subjek)}`"
                            class="brutal-sm brutal-hover shrink-0 bg-accent-400 px-3 py-2 text-sm font-bold text-primary-800"
                        >Balas via Email</a>
                    </div>

                    <dl class="mt-4 grid gap-2 text-xs sm:grid-cols-3">
                        <div>
                            <dt class="font-bold uppercase text-muted">Jenis</dt>
                            <dd>{{ terpilih.label_jenis }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold uppercase text-muted">Diterima</dt>
                            <dd>{{ terpilih.dibuat_pada }}</dd>
                        </div>
                        <div>
                            <dt class="font-bold uppercase text-muted">Status</dt>
                            <dd>{{ terpilih.label_status }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 border-2 border-ink bg-paper-alt p-4 text-sm leading-relaxed whitespace-pre-line">{{ terpilih.pesan }}</div>

                    <!-- Tindak lanjut -->
                    <div class="mt-5 space-y-3">
                        <div>
                            <label :for="`catatan-${terpilih.id}`" class="text-sm font-bold">Catatan internal</label>
                            <textarea
                                :id="`catatan-${terpilih.id}`"
                                v-model="catatan"
                                rows="3"
                                placeholder="Mis. sudah dibalas 27 Sep, menunggu konfirmasi jadwal."
                                class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            />
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="brutal-sm brutal-hover bg-primary-600 px-3 py-2 text-sm font-bold text-paper"
                                @click="ubahStatus(terpilih, 'dibalas')"
                            >Tandai Sudah Dibalas</button>
                            <button
                                type="button"
                                class="brutal-sm brutal-hover bg-paper px-3 py-2 text-sm font-bold"
                                @click="simpanCatatan(terpilih)"
                            >Simpan Catatan</button>
                            <button
                                type="button"
                                class="brutal-sm brutal-hover bg-paper px-3 py-2 text-sm font-bold"
                                @click="ubahStatus(terpilih, 'arsip')"
                            >Arsipkan</button>
                            <button
                                type="button"
                                class="brutal-sm brutal-hover ml-auto bg-accent-100 px-3 py-2 text-sm font-bold"
                                @click="hapus(terpilih)"
                            >Hapus</button>
                        </div>

                        <p v-if="terpilih.dibalas_pada" class="text-xs text-muted">
                            Ditandai dibalas pada {{ terpilih.dibalas_pada }}.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </PanelLayout>
</template>
