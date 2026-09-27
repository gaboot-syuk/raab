<script setup lang="ts">
import { computed, ref } from 'vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

type Item = {
    kunci: string;
    label: string;
    tipe: string;
    pilihan: Record<string, string> | null;
    nilai_id: string;
    nilai_en: string;
};

const props = defineProps<{
    grup: { nama: string; label: string; item: Item[] }[];
}>();

const page = usePage<{ flash: { sukses?: string; galat?: string } }>();

/** Susun nilai awal: nilai[kunci] = { id, en } */
const nilaiAwal: Record<string, { id: string; en: string }> = {};
for (const kelompok of props.grup) {
    for (const item of kelompok.item) {
        nilaiAwal[item.kunci] = { id: item.nilai_id ?? '', en: item.nilai_en ?? '' };
    }
}

const form = useForm<{ nilai: Record<string, { id: string; en: string }> }>({ nilai: nilaiAwal });

const grupAktif = ref(props.grup[0]?.nama ?? '');
const bahasa = ref<'id' | 'en'>('id');

const itemAktif = computed(
    () => props.grup.find((g) => g.nama === grupAktif.value)?.item ?? [],
);

const jumlahKosongEn = computed(() =>
    Object.values(form.nilai).filter((n) => (n.en ?? '').trim() === '').length,
);

function simpan() {
    form.put('/panel/pengaturan', { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Pengaturan Situs" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Pengaturan Situs</h1>
                <p class="mt-1 text-sm text-muted">
                    Identitas, kontak sekretariat, SEO, dan parameter perpustakaan.
                    Perubahan langsung tampil di halaman publik.
                </p>
            </div>

            <p
                v-if="page.props.flash?.sukses"
                class="brutal-sm border-success bg-success/10 px-4 py-3 text-sm font-semibold"
            >
                {{ page.props.flash.sukses }}
            </p>

            <!-- Kelompok pengaturan -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="kelompok in grup"
                    :key="kelompok.nama"
                    type="button"
                    class="border-2 border-ink px-3 py-2 text-xs font-bold uppercase"
                    :class="grupAktif === kelompok.nama ? 'bg-accent-400 text-primary-800' : 'bg-paper'"
                    @click="grupAktif = kelompok.nama"
                >
                    {{ kelompok.label }}
                </button>
            </div>

            <!-- Pengalih bahasa -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex border-2 border-ink">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-bold uppercase"
                        :class="bahasa === 'id' ? 'bg-accent-400 text-primary-800' : 'bg-paper'"
                        @click="bahasa = 'id'"
                    >Indonesia</button>
                    <button
                        type="button"
                        class="border-l-2 border-ink px-4 py-2 text-sm font-bold uppercase"
                        :class="bahasa === 'en' ? 'bg-accent-400 text-primary-800' : 'bg-paper'"
                        @click="bahasa = 'en'"
                    >Inggris</button>
                </div>
                <p class="text-xs text-muted">
                    {{ jumlahKosongEn }} kunci belum punya versi Inggris (otomatis memakai versi Indonesia).
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="simpan">
                <div
                    v-for="item in itemAktif"
                    :key="item.kunci"
                    class="brutal bg-paper p-5"
                >
                    <label class="block text-xs font-bold uppercase tracking-wide">{{ item.label }}</label>
                    <p class="mt-0.5 font-mono text-[11px] text-muted">{{ item.kunci }}</p>

                    <!-- Boolean -->
                    <label v-if="item.tipe === 'boolean'" class="mt-3 flex items-center gap-2 text-sm font-semibold">
                        <input
                            type="checkbox"
                            class="h-4 w-4 border-2 border-ink"
                            :checked="form.nilai[item.kunci][bahasa] === '1'"
                            @change="form.nilai[item.kunci][bahasa] = ($event.target as HTMLInputElement).checked ? '1' : '0'"
                        >
                        Aktif
                    </label>

                    <!-- Area teks -->
                    <textarea
                        v-else-if="item.tipe === 'area'"
                        v-model="form.nilai[item.kunci][bahasa]"
                        rows="3"
                        class="mt-2 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    ></textarea>

                    <!-- Angka -->
                    <input
                        v-else-if="item.tipe === 'angka'"
                        v-model="form.nilai[item.kunci][bahasa]"
                        type="number"
                        class="mt-2 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm sm:max-w-xs"
                    >

                    <!-- Pilihan (dropdown) -->
                    <select
                        v-else-if="item.pilihan"
                        v-model="form.nilai[item.kunci][bahasa]"
                        class="mt-2 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm sm:max-w-md"
                    >
                        <option v-for="(label, nilai) in item.pilihan" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>

                    <!-- Teks & URL -->
                    <input
                        v-else
                        v-model="form.nilai[item.kunci][bahasa]"
                        :type="item.tipe === 'url' ? 'url' : 'text'"
                        class="mt-2 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    >
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="brutal brutal-hover bg-primary-600 px-6 py-3 font-bold text-paper disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Pengaturan' }}
                    </button>

                    <p v-if="form.isDirty" class="text-xs font-bold uppercase text-warning">
                        Ada perubahan belum disimpan
                    </p>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
