<script setup lang="ts">
/*
 * Slider "Tampilan Utama" pada beranda.
 * Gambar latar dipilih dari Pustaka Media (unggah berkas dulu di sana).
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Slider {
    id: number;
    judul: Record<string, string | null>;
    subjudul: Record<string, string | null>;
    label_tombol: Record<string, string | null>;
    tautan_tombol: string | null;
    media_id: number | null;
    gambar: string | null;
    urutan: number;
    aktif: boolean;
    mulai_pada: string | null;
    berakhir_pada: string | null;
    sedang_tampil: boolean;
}

const props = defineProps<{
    daftar: Slider[];
    pilihanGambar: { id: number; nama: string; url: string }[];
}>();

const modal = ref(false);
const disunting = ref<Slider | null>(null);

const form = useForm({
    judul: { id: '', en: '' } as Record<string, string>,
    subjudul: { id: '', en: '' } as Record<string, string>,
    label_tombol: { id: '', en: '' } as Record<string, string>,
    tautan_tombol: '',
    media_id: null as number | null,
    urutan: 0,
    aktif: true,
    mulai_pada: '',
    berakhir_pada: '',
});

const judulModal = computed(() => (disunting.value ? 'Ubah Slider' : 'Tambah Slider'));

function bukaTambah() {
    disunting.value = null;
    form.reset();
    form.clearErrors();
    form.urutan = props.daftar.length + 1;
    form.aktif = true;
    modal.value = true;
}

function bukaUbah(slider: Slider) {
    disunting.value = slider;
    form.clearErrors();
    form.judul = { id: slider.judul.id ?? '', en: slider.judul.en ?? '' };
    form.subjudul = { id: slider.subjudul.id ?? '', en: slider.subjudul.en ?? '' };
    form.label_tombol = { id: slider.label_tombol.id ?? '', en: slider.label_tombol.en ?? '' };
    form.tautan_tombol = slider.tautan_tombol ?? '';
    form.media_id = slider.media_id;
    form.urutan = slider.urutan;
    form.aktif = slider.aktif;
    form.mulai_pada = slider.mulai_pada ?? '';
    form.berakhir_pada = slider.berakhir_pada ?? '';
    modal.value = true;
}

function simpan() {
    const opsi = {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = false;
            form.reset();
        },
    };

    if (disunting.value) {
        form.put(`/panel/slider/${disunting.value.id}`, opsi);
    } else {
        form.post('/panel/slider', opsi);
    }
}

function hapus(slider: Slider) {
    if (!confirm(`Hapus slider "${slider.judul.id}"?`)) {
        return;
    }

    router.delete(`/panel/slider/${slider.id}`, { preserveScroll: true });
}

/** Geser urutan: -1 naik, +1 turun. */
function geser(indeks: number, arah: number) {
    const tujuan = indeks + arah;

    if (tujuan < 0 || tujuan >= props.daftar.length) {
        return;
    }

    const id = props.daftar.map((s) => s.id);
    [id[indeks], id[tujuan]] = [id[tujuan], id[indeks]];

    router.put('/panel/slider/urutan', { urutan: id }, { preserveScroll: true });
}

function alihkanAktif(slider: Slider) {
    router.put(
        `/panel/slider/${slider.id}`,
        {
            judul: slider.judul,
            subjudul: slider.subjudul,
            label_tombol: slider.label_tombol,
            tautan_tombol: slider.tautan_tombol,
            media_id: slider.media_id,
            urutan: slider.urutan,
            aktif: !slider.aktif,
            mulai_pada: slider.mulai_pada,
            berakhir_pada: slider.berakhir_pada,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <PanelLayout>
        <Head title="Slider Beranda" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Slider Beranda</h1>
                    <p class="mt-1 text-sm text-muted">
                        Banner besar di bagian atas beranda. Urutan terkecil tampil lebih dahulu.
                    </p>
                </div>

                <div class="flex gap-2">
                    <a href="/panel/media" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                        Pustaka Media
                    </a>
                    <button type="button" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" @click="bukaTambah">
                        + Tambah Slider
                    </button>
                </div>
            </div>

            <PesanHasil />

            <p v-if="!daftar.length" class="brutal bg-paper-alt px-4 py-10 text-center text-sm text-muted">
                Belum ada slider. Beranda memakai teks bawaan sampai slider pertama dibuat.
            </p>

            <ul v-else class="space-y-3">
                <li v-for="(slider, indeks) in daftar" :key="slider.id" class="brutal bg-paper p-4 sm:flex sm:gap-5">
                    <div class="h-28 w-full shrink-0 border-2 border-ink bg-paper-alt sm:w-44">
                        <img v-if="slider.gambar" :src="slider.gambar" :alt="slider.judul.id ?? ''" class="h-full w-full object-cover">
                        <p v-else class="grid h-full place-items-center text-xs text-muted">Tanpa gambar</p>
                    </div>

                    <div class="mt-3 min-w-0 flex-1 sm:mt-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="border-2 border-ink bg-paper-alt px-1.5 text-[11px] font-bold">#{{ slider.urutan }}</span>
                            <span
                                class="border-2 border-ink px-1.5 text-[11px] font-bold uppercase"
                                :class="slider.sedang_tampil ? 'bg-success/25' : 'bg-paper-alt text-muted'"
                            >{{ slider.sedang_tampil ? 'Tampil' : slider.aktif ? 'Terjadwal' : 'Nonaktif' }}</span>
                        </div>

                        <h2 class="mt-2 font-display text-lg">{{ slider.judul.id ?? '(tanpa judul)' }}</h2>
                        <p class="text-sm text-muted">{{ slider.judul.en || 'Versi Inggris belum diisi' }}</p>
                        <p v-if="slider.subjudul.id" class="mt-1 line-clamp-2 text-sm">{{ slider.subjudul.id }}</p>
                        <p v-if="slider.mulai_pada || slider.berakhir_pada" class="mt-1 text-xs text-muted">
                            Jadwal: {{ slider.mulai_pada || '—' }} s/d {{ slider.berakhir_pada || '—' }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-1">
                            <button type="button" class="brutal-sm bg-paper px-2 py-1 text-xs font-bold" :disabled="indeks === 0" @click="geser(indeks, -1)">↑ Naik</button>
                            <button type="button" class="brutal-sm bg-paper px-2 py-1 text-xs font-bold" :disabled="indeks === daftar.length - 1" @click="geser(indeks, 1)">↓ Turun</button>
                            <button type="button" class="brutal-sm bg-paper px-2 py-1 text-xs font-bold" @click="alihkanAktif(slider)">
                                {{ slider.aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                            <button type="button" class="brutal-sm bg-paper px-2 py-1 text-xs font-bold" @click="bukaUbah(slider)">Ubah</button>
                            <button type="button" class="brutal-sm bg-accent-100 px-2 py-1 text-xs font-bold" @click="hapus(slider)">Hapus</button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- ===== Modal slider ===== -->
        <div v-if="modal" class="fixed inset-0 z-50 grid place-items-start overflow-y-auto bg-ink/60 p-4" @click.self="modal = false">
            <form class="brutal mx-auto my-6 w-full max-w-2xl bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">{{ judulModal }}</h2>

                <div class="mt-4 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="judul-id" class="block text-sm font-bold">Judul (Indonesia)</label>
                            <input id="judul-id" v-model="form.judul.id" type="text" required maxlength="190" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="judul-en" class="block text-sm font-bold">Judul (Inggris)</label>
                            <input id="judul-en" v-model="form.judul.en" type="text" maxlength="190" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="subjudul-id" class="block text-sm font-bold">Subjudul (Indonesia)</label>
                            <textarea id="subjudul-id" v-model="form.subjudul.id" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label for="subjudul-en" class="block text-sm font-bold">Subjudul (Inggris)</label>
                            <textarea id="subjudul-en" v-model="form.subjudul.en" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="tombol-id" class="block text-sm font-bold">Label tombol (Indonesia)</label>
                            <input id="tombol-id" v-model="form.label_tombol.id" type="text" maxlength="60" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="tombol-en" class="block text-sm font-bold">Label tombol (Inggris)</label>
                            <input id="tombol-en" v-model="form.label_tombol.en" type="text" maxlength="60" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="tautan" class="block text-sm font-bold">Tautan tombol</label>
                        <input id="tautan" v-model="form.tautan_tombol" type="text" placeholder="/pendaftaran/mapaba" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label for="gambar" class="block text-sm font-bold">Gambar latar</label>
                        <select id="gambar" v-model="form.media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— Tanpa gambar —</option>
                            <option v-for="g in pilihanGambar" :key="g.id" :value="g.id">{{ g.nama }}</option>
                        </select>
                        <p class="mt-1 text-xs text-muted">
                            Belum ada gambar? Unggah dulu melalui Pustaka Media.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="urutan" class="block text-sm font-bold">Urutan</label>
                            <input id="urutan" v-model.number="form.urutan" type="number" min="0" max="999" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="mulai" class="block text-sm font-bold">Mulai tampil</label>
                            <input id="mulai" v-model="form.mulai_pada" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="berakhir" class="block text-sm font-bold">Berakhir</label>
                            <input id="berakhir" v-model="form.berakhir_pada" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.aktif" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        Aktifkan slider ini
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modal = false">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="form.processing">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
