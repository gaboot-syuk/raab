<script setup lang="ts">
/*
 * Peran & Izin: mengatur hak akses setiap peran.
 * Peran Superadmin dikunci dan selalu memegang seluruh izin.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Peran {
    id: number;
    nama: string;
    label: string;
    jumlah_pengguna: number;
    terkunci: boolean;
    izin: string[];
}

interface KelompokIzin {
    prefiks: string;
    label: string;
    izin: { nama: string; label: string }[];
}

const props = defineProps<{
    peran: Peran[];
    katalog: KelompokIzin[];
    jumlahIzin: number;
}>();

const peranAktifId = ref<number>(props.peran[0]?.id ?? 0);
const peranAktif = computed<Peran | null>(
    () => props.peran.find((p) => p.id === peranAktifId.value) ?? null,
);

/** Salinan izin terpilih per peran, disunting sebelum disimpan. */
const izinTerpilih = ref<string[]>([]);
const cari = ref('');

watch(
    () => peranAktifId.value,
    () => {
        izinTerpilih.value = [...(peranAktif.value?.izin ?? [])];
    },
    { immediate: true },
);

const izinAktif = computed(() => new Set(izinTerpilih.value));

const katalogTersaring = computed(() => {
    const kunci = cari.value.trim().toLowerCase();

    if (!kunci) {
        return props.katalog;
    }

    return props.katalog
        .map((kelompok) => ({
            ...kelompok,
            izin: kelompok.izin.filter(
                (izin) =>
                    izin.label.toLowerCase().includes(kunci) ||
                    izin.nama.toLowerCase().includes(kunci),
            ),
        }))
        .filter((kelompok) => kelompok.izin.length > 0);
});

function alihkan(izin: string) {
    const posisi = izinTerpilih.value.indexOf(izin);

    if (posisi === -1) {
        izinTerpilih.value.push(izin);
    } else {
        izinTerpilih.value.splice(posisi, 1);
    }
}

function pilihKelompok(kelompok: KelompokIzin, nilai: boolean) {
    for (const izin of kelompok.izin) {
        const posisi = izinTerpilih.value.indexOf(izin.nama);

        if (nilai && posisi === -1) {
            izinTerpilih.value.push(izin.nama);
        } else if (!nilai && posisi !== -1) {
            izinTerpilih.value.splice(posisi, 1);
        }
    }
}

/** Kelompok tercentang sebagian. */
function sebagian(kelompok: KelompokIzin): boolean {
    const jumlah = kelompok.izin.filter((i) => izinAktif.value.has(i.nama)).length;

    return jumlah > 0 && jumlah < kelompok.izin.length;
}

function simpan() {
    if (!peranAktif.value) {
        return;
    }

    router.put(
        `/panel/peran/${peranAktif.value.id}`,
        { izin: izinTerpilih.value },
        { preserveScroll: true },
    );
}

function pilihSemua() {
    izinTerpilih.value = props.katalog.flatMap((kelompok) => kelompok.izin.map((i) => i.nama));
}

function kosongkan() {
    izinTerpilih.value = [];
}
</script>

<template>
    <PanelLayout>
        <Head title="Peran & Izin" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Peran &amp; Izin</h1>
                <p class="mt-1 text-sm text-muted">
                    {{ jumlahIzin }} izin tersedia. Centang tindakan yang boleh dilakukan setiap peran.
                </p>
            </div>

            <PesanHasil />

            <div class="grid gap-4 lg:grid-cols-[16rem_1fr]">
                <!-- ===== Daftar peran ===== -->
                <ul class="space-y-2">
                    <li v-for="p in peran" :key="p.id">
                        <button
                            type="button"
                            class="w-full border-2 border-ink p-3 text-left"
                            :class="peranAktifId === p.id ? 'bg-accent-400' : 'bg-paper hover:bg-accent-100'"
                            @click="peranAktifId = p.id"
                        >
                            <p class="font-bold">{{ p.label }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ p.terkunci ? 'Semua izin' : p.izin.length + ' izin' }}
                                · {{ p.jumlah_pengguna }} akun
                            </p>
                        </button>
                    </li>
                </ul>

                <!-- ===== Matriks izin ===== -->
                <div v-if="peranAktif" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b-2 border-ink pb-4">
                        <div>
                            <h2 class="font-display text-lg">{{ peranAktif.label }}</h2>
                            <p class="text-sm text-muted">{{ izinTerpilih.length }} izin dicentang.</p>
                        </div>

                        <div class="flex gap-2">
                            <button
                                v-if="!peranAktif.terkunci"
                                type="button"
                                class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                                @click="pilihSemua"
                            >Centang semua</button>
                            <button
                                v-if="!peranAktif.terkunci"
                                type="button"
                                class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                                @click="kosongkan"
                            >Kosongkan</button>
                            <button
                                type="button"
                                class="brutal-sm brutal-hover bg-primary-600 px-4 py-1.5 text-sm font-bold text-paper disabled:opacity-50"
                                :disabled="peranAktif.terkunci"
                                @click="simpan"
                            >Simpan</button>
                        </div>
                    </div>

                    <p v-if="peranAktif.terkunci" class="brutal-sm mt-4 border-ink bg-accent-100 px-3 py-2 text-sm font-semibold">
                        Peran Superadmin selalu memegang seluruh izin dan tidak dapat diubah, agar panel tidak pernah terkunci.
                    </p>

                    <input
                        v-model="cari"
                        type="search"
                        placeholder="Cari izin…"
                        class="brutal-sm mt-4 w-full bg-paper-alt px-3 py-2 text-sm"
                    >

                    <div class="mt-4 space-y-5">
                        <fieldset v-for="kelompok in katalogTersaring" :key="kelompok.prefiks" class="border-2 border-ink/20 p-3">
                            <legend class="px-1">
                                <label class="flex items-center gap-2 text-sm font-bold">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 border-2 border-ink"
                                        :checked="kelompok.izin.every((i) => izinAktif.has(i.nama))"
                                        :indeterminate.prop="sebagian(kelompok)"
                                        :disabled="peranAktif.terkunci"
                                        @change="pilihKelompok(kelompok, ($event.target as HTMLInputElement).checked)"
                                    >
                                    {{ kelompok.label }}
                                </label>
                            </legend>

                            <div class="mt-2 grid gap-1 sm:grid-cols-2">
                                <label
                                    v-for="izin in kelompok.izin"
                                    :key="izin.nama"
                                    class="flex items-start gap-2 text-sm"
                                    :title="izin.nama"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 h-4 w-4 shrink-0 border-2 border-ink"
                                        :checked="izinAktif.has(izin.nama)"
                                        :disabled="peranAktif.terkunci"
                                        @change="alihkan(izin.nama)"
                                    >
                                    <span>{{ izin.label }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <p v-if="!katalogTersaring.length" class="text-sm text-muted">Tidak ada izin yang cocok.</p>
                    </div>
                </div>
            </div>
        </div>
    </PanelLayout>
</template>
