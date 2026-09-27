<script setup lang="ts">
import { computed, ref } from 'vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    halaman: {
        id: number;
        kunci: string | null;
        tipe: string;
        status: string;
        judul: Record<string, string>;
        slug: Record<string, string>;
        ringkasan: Record<string, string>;
        konten: Record<string, string>;
        seo_judul: Record<string, string>;
        seo_deskripsi: Record<string, string>;
        kelengkapan_en: number;
    };
    tautan_publik: string | null;
}>();

const page = usePage<{ flash: { sukses?: string; galat?: string } }>();

const bahasa = ref<'id' | 'en'>('id');

const form = useForm({
    judul: {
        id: props.halaman.judul.id ?? '',
        en: props.halaman.judul.en ?? '',
    },
    slug: {
        id: props.halaman.slug.id ?? '',
        en: props.halaman.slug.en ?? '',
    },
    ringkasan: {
        id: props.halaman.ringkasan.id ?? '',
        en: props.halaman.ringkasan.en ?? '',
    },
    konten: {
        id: props.halaman.konten.id ?? '',
        en: props.halaman.konten.en ?? '',
    },
    seo_judul: {
        id: props.halaman.seo_judul.id ?? '',
        en: props.halaman.seo_judul.en ?? '',
    },
    seo_deskripsi: {
        id: props.halaman.seo_deskripsi.id ?? '',
        en: props.halaman.seo_deskripsi.en ?? '',
    },
    status: props.halaman.status,
});

/** Kelengkapan terjemahan Inggris dihitung langsung dari isian saat ini. */
const kelengkapan = computed(() => {
    const bidang = ['judul', 'ringkasan', 'konten', 'seo_judul', 'seo_deskripsi'] as const;
    const terisi = bidang.filter((k) => (form[k].en ?? '').trim() !== '').length;

    return Math.round((terisi / bidang.length) * 100);
});

function simpan() {
    form.put(`/panel/halaman/${props.halaman.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <PanelLayout>
        <Head :title="`Sunting: ${form.judul.id || 'Halaman'}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <!-- Kepala -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/panel/halaman" class="text-xs font-bold uppercase underline">← Halaman Statis</Link>
                    <h1 class="mt-1 font-display text-2xl">{{ form.judul.id || 'Halaman' }}</h1>
                </div>

                <a
                    v-if="tautan_publik"
                    :href="tautan_publik"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="brutal-sm bg-paper px-3 py-2 text-xs font-bold"
                >Lihat di situs ↗</a>
            </div>

            <p
                v-if="page.props.flash?.sukses"
                class="brutal-sm border-success bg-success/10 px-4 py-3 text-sm font-semibold"
            >
                {{ page.props.flash.sukses }}
            </p>

            <!-- Pengalih bahasa penyuntingan -->
            <div class="flex items-center gap-3">
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

                <div class="min-w-32">
                    <p class="text-[11px] font-bold uppercase text-muted">Kelengkapan EN</p>
                    <div class="mt-1 h-3 border-2 border-ink bg-paper-alt">
                        <div class="h-full bg-accent-400" :style="{ width: kelengkapan + '%' }"></div>
                    </div>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="simpan">
                <p class="brutal-sm bg-primary-50 px-3 py-2 text-xs font-semibold">
                    Bahasa Indonesia wajib diisi. Versi Inggris boleh menyusul — bila kosong, pengunjung
                    otomatis melihat versi Indonesia.
                </p>

                <!-- Judul -->
                <div class="brutal bg-paper p-5">
                    <label class="block text-xs font-bold uppercase tracking-wide">
                        Judul <span v-if="bahasa === 'id'">(wajib)</span>
                    </label>
                    <input
                        v-model="form.judul[bahasa]"
                        type="text"
                        class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    >
                    <p v-if="form.errors['judul.id']" class="mt-1 text-sm font-semibold text-danger">
                        {{ form.errors['judul.id'] }}
                    </p>
                </div>

                <!-- Slug -->
                <div class="brutal bg-paper p-5">
                    <label class="block text-xs font-bold uppercase tracking-wide">Slug (alamat halaman)</label>
                    <input
                        v-model="form.slug[bahasa]"
                        type="text"
                        class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 font-mono text-sm"
                    >
                    <p class="mt-1 text-xs text-muted">
                        Huruf kecil, angka, dan tanda hubung. Contoh: <code>sejarah-rayon</code>
                    </p>
                    <p v-if="form.errors['slug.id']" class="mt-1 text-sm font-semibold text-danger">
                        {{ form.errors['slug.id'] }}
                    </p>
                </div>

                <!-- Ringkasan -->
                <div class="brutal bg-paper p-5">
                    <label class="block text-xs font-bold uppercase tracking-wide">Ringkasan</label>
                    <textarea
                        v-model="form.ringkasan[bahasa]"
                        rows="2"
                        class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    ></textarea>
                    <p class="mt-1 text-xs text-muted">Tampil sebagai pengantar di bawah judul.</p>
                </div>

                <!-- Konten -->
                <div class="brutal bg-paper p-5">
                    <label class="block text-xs font-bold uppercase tracking-wide">
                        Isi Halaman <span v-if="bahasa === 'id'">(wajib)</span>
                    </label>
                    <textarea
                        v-model="form.konten[bahasa]"
                        rows="14"
                        class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 font-mono text-sm"
                    ></textarea>
                    <p class="mt-1 text-xs text-muted">
                        Mendukung HTML sederhana: <code>&lt;p&gt;</code>, <code>&lt;h2&gt;</code>,
                        <code>&lt;ul&gt;&lt;li&gt;</code>, <code>&lt;strong&gt;</code>.
                        Penyunting visual (WYSIWYG) menyusul.
                    </p>
                    <p v-if="form.errors['konten.id']" class="mt-1 text-sm font-semibold text-danger">
                        {{ form.errors['konten.id'] }}
                    </p>
                </div>

                <!-- SEO -->
                <div class="brutal bg-paper p-5">
                    <h2 class="font-display text-sm uppercase">Mesin Pencari</h2>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label class="block text-xs font-bold uppercase">Judul SEO</label>
                            <input
                                v-model="form.seo_judul[bahasa]"
                                type="text"
                                class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2 text-sm"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase">Deskripsi SEO</label>
                            <textarea
                                v-model="form.seo_deskripsi[bahasa]"
                                rows="2"
                                class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="brutal bg-paper p-5">
                    <label class="block text-xs font-bold uppercase tracking-wide">Status</label>
                    <select
                        v-model="form.status"
                        class="mt-1 w-full border-2 border-ink bg-paper px-3 py-2.5 text-sm"
                    >
                        <option value="draft">Draf (belum tampil di publik)</option>
                        <option value="terbit">Terbit (tampil di publik)</option>
                    </select>
                </div>

                <!-- Aksi -->
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="brutal brutal-hover bg-primary-600 px-6 py-3 font-bold text-paper disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                    </button>

                    <Link href="/panel/halaman" class="border-2 border-ink bg-paper px-6 py-3 font-bold">
                        Batal
                    </Link>

                    <p v-if="form.isDirty" class="text-xs font-bold uppercase text-warning">
                        Ada perubahan belum disimpan
                    </p>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
