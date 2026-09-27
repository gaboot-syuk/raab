<script setup lang="ts">
/*
 * Penyunting karya untuk kader & alumni.
 * Lebih ringkas daripada penyunting di panel: tanpa berita acara, tanpa
 * penjadwalan, dan tanpa tombol terbitkan — penerbitan wewenang pengelola.
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import EditorKaya from '@/Components/Panel/EditorKaya.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    karya: {
        id: number;
        tipe: string;
        label_status: string;
        status: string;
        kategori_id: number | null;
        judul: Record<string, string | null>;
        ringkasan: Record<string, string | null>;
        konten: Record<string, string | null>;
        tag: string;
        catatan_review: string | null;
        boleh_kirim: boolean;
    } | null;
    pilihanTipe: Record<string, string>;
    daftarKategori: { id: number; nama: string }[];
}>();

const kolom = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm';
const bahasa = ref<'id' | 'en'>('id');

const form = useForm({
    tipe: props.karya?.tipe ?? 'opini',
    kategori_id: props.karya?.kategori_id ?? null,
    judul: { id: props.karya?.judul?.id ?? '', en: props.karya?.judul?.en ?? '' } as Record<string, string>,
    ringkasan: { id: props.karya?.ringkasan?.id ?? '', en: props.karya?.ringkasan?.en ?? '' } as Record<string, string>,
    konten: { id: props.karya?.konten?.id ?? '', en: props.karya?.konten?.en ?? '' } as Record<string, string>,
    tag: props.karya?.tag ?? '',
});

const terkirim = computed(() => props.karya !== null && !props.karya.boleh_kirim);

function simpan() {
    if (props.karya) {
        form.put(`/karya/${props.karya.id}`, { preserveScroll: true });
    } else {
        form.post('/karya', { preserveScroll: true });
    }
}

function kirimReview() {
    if (!props.karya) {
        return;
    }

    router.post(`/karya/${props.karya.id}/kirim`, {}, { preserveScroll: true });
}
</script>

<template>
    <AnggotaLayout>
        <Head :title="karya ? 'Sunting Karya' : 'Karya Baru'" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link href="/karya" class="text-xs font-bold uppercase underline">← Karya Saya</Link>
                    <h1 class="mt-2 font-display text-2xl">{{ karya ? 'Sunting Karya' : 'Karya Baru' }}</h1>
                    <p v-if="karya" class="mt-1 text-sm text-muted">Status: <strong>{{ karya.label_status }}</strong></p>
                </div>

                <div class="flex gap-2">
                    <button
                        v-if="karya && karya.boleh_kirim"
                        type="button"
                        class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                        @click="kirimReview"
                    >Kirim untuk Review</button>
                </div>
            </div>

            <PesanHasil />

            <p v-if="terkirim" class="brutal-sm border-ink bg-paper-alt px-4 py-3 text-sm text-muted">
                Karya ini sudah dikirim atau sudah terbit, sehingga tidak dapat disunting lagi.
            </p>

            <div v-if="karya?.catatan_review" class="brutal-sm border-ink bg-accent-100 px-4 py-3 text-sm">
                <p class="font-bold">Catatan dari pengelola konten</p>
                <p class="mt-1">{{ karya.catatan_review }}</p>
            </div>

            <form class="space-y-6" @submit.prevent="simpan">
                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Jenis &amp; Kategori</legend>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="tipe" class="block text-sm font-bold">Jenis karya *</label>
                            <select id="tipe" v-model="form.tipe" required :class="kolom">
                                <option v-for="(label, nilai) in pilihanTipe" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="kategori_id" class="block text-sm font-bold">Kategori</label>
                            <select id="kategori_id" v-model="form.kategori_id" :class="kolom">
                                <option :value="null">— Belum ditentukan —</option>
                                <option v-for="k in daftarKategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                            <p class="mt-1 text-xs text-muted">Pengelola konten dapat menyesuaikan kategori nanti.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Isi Karya</legend>

                    <div class="flex items-center gap-2">
                        <button
                            v-for="b in (['id', 'en'] as const)"
                            :key="b"
                            type="button"
                            class="brutal-sm px-3 py-1.5 text-sm font-bold"
                            :class="bahasa === b ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                            @click="bahasa = b"
                        >{{ b === 'id' ? 'Indonesia' : 'Inggris' }}</button>
                        <span class="text-xs text-muted">Versi Inggris boleh dikosongkan.</span>
                    </div>

                    <div>
                        <label :for="`judul-${bahasa}`" class="block text-sm font-bold">Judul {{ bahasa === 'id' ? '*' : '' }}</label>
                        <input :id="`judul-${bahasa}`" v-model="form.judul[bahasa]" type="text" maxlength="190" :class="kolom">
                    </div>

                    <div>
                        <label :for="`ringkasan-${bahasa}`" class="block text-sm font-bold">Ringkasan singkat</label>
                        <textarea :id="`ringkasan-${bahasa}`" v-model="form.ringkasan[bahasa]" rows="2" maxlength="500" :class="kolom" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold">Isi karya {{ bahasa === 'id' ? '*' : '' }}</label>

                        <EditorKaya v-model="form.konten[bahasa]" :placeholder="bahasa === 'id' ? 'Tulis karyamu di sini…' : 'Write the English version…'" />

                        <p class="mt-1 text-xs text-muted">
                            Minimal 50 karakter. Gunakan tombol <strong>🖼</strong> untuk menyisipkan gambar
                            (salin tautannya dari Pustaka Media), dan tombol <strong>❝</strong> untuk kutipan.
                        </p>
                    </div>

                    <div>
                        <label for="tag" class="block text-sm font-bold">Tag</label>
                        <input id="tag" v-model="form.tag" type="text" placeholder="pisahkan dengan koma, mis. literasi, mapaba" :class="kolom">
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800" :disabled="form.processing || terkirim">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan sebagai Draf' }}
                    </button>
                    <Link href="/karya" class="brutal bg-paper px-6 py-3 font-bold">Kembali</Link>
                </div>
            </form>
        </div>
    </AnggotaLayout>
</template>
