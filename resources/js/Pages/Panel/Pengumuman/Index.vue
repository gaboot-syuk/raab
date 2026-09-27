<script setup lang="ts">
/*
 * Panel pengumuman.
 *
 * Yang paling sering salah di modul ini: mengira `tipe` dan `target_audience`
 * itu hal yang sama. Tipe menentukan DI MANA ia tayang; audiens menentukan
 * SIAPA yang boleh membacanya. Pengumuman bertipe publik yang audiensnya hanya
 * "Pengurus" akan muncul di halaman publik tetapi tidak bisa dibuka siapa pun —
 * karena itu layanan menolaknya, dan halaman ini menjelaskannya.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Pengumuman {
    id: number;
    slug: string;
    judul: string;
    isi: string;
    tipe: string;
    label_tipe: string;
    audiens: string[];
    audiens_teks: string[];
    is_pinned: boolean;
    publish_at: string;
    publish_at_teks: string | null;
    expire_at: string | null;
    expire_at_teks: string | null;
    label_waktu: string;
    diperbarui: string | null;
}

const props = defineProps<{
    daftar: Pengumuman[];
    rekap: { total: number; tayang: number; terjadwal: number; kedaluwarsa: number; disematkan: number; publik: number };
    saringan: string;
    pilihanTipe: Record<string, string>;
    pilihanAudiens: Record<string, string>;
    catatan: string;
}>();

const statusSaring = ref(props.saringan);
const sedangSunting = ref<number | null>(null);

const form = useForm({
    judul: '',
    isi: '',
    tipe: 'internal',
    target_audience: [] as string[],
    is_pinned: false,
    publish_at: '',
    expire_at: '',
});

const formSunting = useForm({
    judul: '',
    isi: '',
    tipe: 'internal',
    target_audience: [] as string[],
    is_pinned: false,
    publish_at: '',
    expire_at: '',
});

function saring(): void {
    router.get('/panel/pengumuman', { saringan: statusSaring.value }, { preserveScroll: true });
}

function simpan(): void {
    form.post('/panel/pengumuman', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.tipe = 'internal';
            form.target_audience = [];
        },
    });
}

function bukaSunting(p: Pengumuman): void {
    if (sedangSunting.value === p.id) {
        sedangSunting.value = null;
        return;
    }

    sedangSunting.value = p.id;
    formSunting.judul = p.judul;
    formSunting.isi = p.isi;
    formSunting.tipe = p.tipe;
    formSunting.target_audience = [...p.audiens];
    formSunting.is_pinned = p.is_pinned;
    formSunting.publish_at = p.publish_at ?? '';
    formSunting.expire_at = p.expire_at ?? '';
}

function perbarui(p: Pengumuman): void {
    formSunting.post(`/panel/pengumuman/${p.id}`, {
        preserveScroll: true,
        onSuccess: () => (sedangSunting.value = null),
    });
}

function sematkan(p: Pengumuman): void {
    router.post(`/panel/pengumuman/${p.id}/sematkan`, { is_pinned: !p.is_pinned }, { preserveScroll: true });
}

function hapus(p: Pengumuman): void {
    if (!confirm(`Hapus pengumuman "${p.judul}"? Isinya masih tersimpan di basis data bila perlu ditelusuri.`)) {
        return;
    }

    router.delete(`/panel/pengumuman/${p.id}`, { preserveScroll: true });
}

function kelasWaktu(label: string): string {
    if (label === 'Tayang') return 'bg-accent-100';
    if (label === 'Terjadwal') return 'bg-paper-alt';

    return 'bg-accent-400';
}
</script>

<template>
    <Head title="Pengumuman" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Pengumuman</h1>
                    <p class="text-sm text-muted">Kabar resmi pengurus, untuk publik maupun kalangan sendiri.</p>
                </div>

                <a href="/panel/pengumuman/ekspor" class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold">Ekspor CSV</a>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

            <!-- ===== Rekap ===== -->
            <section class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div class="brutal bg-accent-100 p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Tayang</p>
                    <p class="font-display text-2xl">{{ props.rekap.tayang }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Terjadwal</p>
                    <p class="font-display text-2xl">{{ props.rekap.terjadwal }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Kedaluwarsa</p>
                    <p class="font-display text-2xl">{{ props.rekap.kedaluwarsa }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Disematkan</p>
                    <p class="font-display text-2xl">{{ props.rekap.disematkan }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Bertipe Publik</p>
                    <p class="font-display text-2xl">{{ props.rekap.publik }}</p>
                </div>
                <div class="brutal bg-paper p-4">
                    <p class="text-[10px] font-bold uppercase text-muted">Total</p>
                    <p class="font-display text-2xl">{{ props.rekap.total }}</p>
                </div>
            </section>

            <!-- ===== Formulir baru ===== -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Pengumuman Baru</h2>

                <form class="mt-3 space-y-4" @submit.prevent="simpan">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                        <input v-model="form.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Isi *</span>
                        <textarea v-model="form.isi" rows="5" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        <span class="text-[11px] text-muted">Pisahkan paragraf dengan baris kosong. Minimal 20 huruf.</span>
                        <span v-if="form.errors.isi" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.isi }}</span>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                            <select v-model="form.tipe" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option v-for="(label, kunci) in props.pilihanTipe" :key="kunci" :value="kunci">{{ label }}</option>
                            </select>
                            <span class="text-[11px] text-muted">Publik: tayang di /pengumuman. Internal: hanya di area anggota.</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Mulai Tayang</span>
                            <input v-model="form.publish_at" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span class="text-[11px] text-muted">Kosongkan untuk tayang sekarang.</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Berakhir</span>
                            <input v-model="form.expire_at" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span class="text-[11px] text-muted">Kosongkan bila tidak ada batas waktu.</span>
                        </label>
                    </div>

                    <fieldset>
                        <legend class="text-xs font-bold uppercase text-muted">Siapa yang boleh membaca *</legend>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label v-for="(label, kunci) in props.pilihanAudiens" :key="kunci" class="flex items-center gap-2">
                                <input v-model="form.target_audience" type="checkbox" :value="kunci" class="h-4 w-4 border-2 border-ink">
                                <span class="text-sm">{{ label }}</span>
                            </label>
                        </div>
                        <span class="text-[11px] text-muted">Pengurus selalu bisa membaca semuanya, supaya yang mengelola pengumuman dapat memeriksa sendiri.</span>
                        <span v-if="form.errors.target_audience" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.target_audience }}</span>
                    </fieldset>

                    <label class="flex items-center gap-2">
                        <input v-model="form.is_pinned" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        <span class="text-sm">Sematkan di puncak daftar</span>
                    </label>

                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Simpan Pengumuman
                    </button>
                </form>
            </section>

            <!-- ===== Daftar ===== -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Tampilkan</span>
                    <select v-model="statusSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua</option>
                        <option value="tayang">Sedang tayang</option>
                        <option value="terjadwal">Terjadwal</option>
                        <option value="kedaluwarsa">Kedaluwarsa</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-5 text-sm text-muted">
                Tidak ada pengumuman yang cocok dengan saringan ini.
            </p>

            <ul v-else class="space-y-4">
                <li v-for="p in props.daftar" :key="p.id" class="brutal bg-paper p-5" :class="p.is_pinned ? 'border-l-8 border-l-accent-400' : ''">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                                {{ p.label_tipe }} · {{ p.publish_at_teks }}
                                <span v-if="p.is_pinned"> · Disematkan</span>
                            </p>
                            <h2 class="font-display text-lg leading-tight">{{ p.judul }}</h2>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ p.isi }}</p>

                            <p class="mt-2 text-xs text-muted">
                                Untuk: <span class="font-bold">{{ p.audiens_teks.join(', ') || '—' }}</span>
                                <template v-if="p.expire_at_teks"> · Berakhir {{ p.expire_at_teks }}</template>
                            </p>
                        </div>

                        <span class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase" :class="kelasWaktu(p.label_waktu)">
                            {{ p.label_waktu }}
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="bukaSunting(p)">
                            {{ sedangSunting === p.id ? 'Tutup' : 'Sunting' }}
                        </button>
                        <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="sematkan(p)">
                            {{ p.is_pinned ? 'Lepas Sematkan' : 'Sematkan' }}
                        </button>
                        <button type="button" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold" @click="hapus(p)">
                            Hapus
                        </button>
                    </div>

                    <form v-if="sedangSunting === p.id" class="mt-3 space-y-3 border-t-2 border-ink/10 pt-3" @submit.prevent="perbarui(p)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Judul *</span>
                            <input v-model="formSunting.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Isi *</span>
                            <textarea v-model="formSunting.isi" rows="4" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        </label>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Jenis</span>
                                <select v-model="formSunting.tipe" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <option v-for="(label, kunci) in props.pilihanTipe" :key="kunci" :value="kunci">{{ label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Mulai Tayang</span>
                                <input v-model="formSunting.publish_at" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>

                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">Berakhir</span>
                                <input v-model="formSunting.expire_at" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            </label>
                        </div>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase text-muted">Siapa yang boleh membaca</legend>
                            <div class="mt-2 flex flex-wrap gap-4">
                                <label v-for="(label, kunci) in props.pilihanAudiens" :key="kunci" class="flex items-center gap-2">
                                    <input v-model="formSunting.target_audience" type="checkbox" :value="kunci" class="h-4 w-4 border-2 border-ink">
                                    <span class="text-sm">{{ label }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <label class="flex items-center gap-2">
                            <input v-model="formSunting.is_pinned" type="checkbox" class="h-4 w-4 border-2 border-ink">
                            <span class="text-sm">Sematkan di puncak daftar</span>
                        </label>

                        <div class="flex gap-2">
                            <button type="submit" :disabled="formSunting.processing" class="brutal-sm bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                Simpan Perubahan
                            </button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="sedangSunting = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
