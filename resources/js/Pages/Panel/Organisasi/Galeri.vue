<script setup lang="ts">
/*
 * Galeri unit & agenda publik.
 *
 * Album dapat menempel pada satu unit, atau berdiri sendiri sebagai galeri
 * rayon (mis. dokumentasi Mapaba) — jadi `unit` boleh kosong.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Album {
    id: number;
    judul: Record<string, string> | null;
    slug: string | null;
    deskripsi: Record<string, string> | null;
    unit_id: number | null;
    unit: string | null;
    tanggal: string | null;
    lokasi: string | null;
    publik: boolean;
    urutan: number;
    jumlah_item: number;
    tautan_publik: string | null;
}

interface Agenda {
    id: number;
    judul: Record<string, string> | null;
    unit_id: number | null;
    unit: string | null;
    deskripsi: Record<string, string> | null;
    mulai: string | null;
    selesai: string | null;
    lokasi: string | null;
    publik: boolean;
    mendatang: boolean;
}

const props = defineProps<{
    album: Album[];
    agenda: Agenda[];
    filterUnit: number | null;
    pilihanUnit: { id: number; label: string }[];
    pilihanGambar: { id: number; nama: string; url: string }[];
}>();

const tab = ref<'album' | 'agenda'>('album');
const suntingAlbum = ref<number | null>(null);
const suntingAgenda = ref<number | null>(null);
const albumTerpilih = ref<Album | null>(null);

const formAlbum = useForm({
    unit_id: null as number | null,
    judul: { id: '', en: '' } as Record<string, string>,
    deskripsi: { id: '', en: '' } as Record<string, string>,
    tanggal: '',
    lokasi: '',
    publik: true,
    urutan: 0,
});

const formFoto = useForm({
    media_id: null as number | null,
    url: '',
    keterangan: { id: '', en: '' } as Record<string, string>,
});

const formAgenda = useForm({
    unit_id: null as number | null,
    judul: { id: '', en: '' } as Record<string, string>,
    deskripsi: { id: '', en: '' } as Record<string, string>,
    mulai: '',
    selesai: '',
    lokasi: '',
    publik: true,
});

function resetAlbum(): void {
    suntingAlbum.value = null;
    formAlbum.reset();
    formAlbum.clearErrors();
}

function suntingAlbumMaju(album: Album): void {
    suntingAlbum.value = album.id;
    formAlbum.clearErrors();
    formAlbum.unit_id = album.unit_id;
    formAlbum.judul = { id: album.judul?.id ?? '', en: album.judul?.en ?? '' };
    formAlbum.deskripsi = { id: album.deskripsi?.id ?? '', en: album.deskripsi?.en ?? '' };
    formAlbum.tanggal = album.tanggal ?? '';
    formAlbum.lokasi = album.lokasi ?? '';
    formAlbum.publik = album.publik;
    formAlbum.urutan = album.urutan;
}

function simpanAlbum(): void {
    const opsi = { preserveScroll: true, onSuccess: () => resetAlbum() };

    if (suntingAlbum.value) {
        formAlbum.put(`/panel/organisasi/galeri/${suntingAlbum.value}`, opsi);
    } else {
        formAlbum.post('/panel/organisasi/galeri', opsi);
    }
}

function hapusAlbum(album: Album): void {
    if (!confirm(`Hapus album "${album.judul?.id}" beserta seluruh fotonya?`)) {
        return;
    }

    router.delete(`/panel/organisasi/galeri/${album.id}`, { preserveScroll: true });
}

function tambahFoto(): void {
    if (!albumTerpilih.value) {
        return;
    }

    formFoto.post(`/panel/organisasi/galeri/${albumTerpilih.value.id}/foto`, {
        preserveScroll: true,
        onSuccess: () => formFoto.reset(),
    });
}

function hapusFoto(id: number): void {
    router.delete(`/panel/organisasi/galeri/foto/${id}`, { preserveScroll: true });
}

function resetAgenda(): void {
    suntingAgenda.value = null;
    formAgenda.reset();
    formAgenda.clearErrors();
}

function suntingAgendaMaju(agenda: Agenda): void {
    suntingAgenda.value = agenda.id;
    formAgenda.clearErrors();
    formAgenda.unit_id = agenda.unit_id;
    formAgenda.judul = { id: agenda.judul?.id ?? '', en: agenda.judul?.en ?? '' };
    formAgenda.deskripsi = { id: agenda.deskripsi?.id ?? '', en: agenda.deskripsi?.en ?? '' };
    formAgenda.mulai = agenda.mulai ?? '';
    formAgenda.selesai = agenda.selesai ?? '';
    formAgenda.lokasi = agenda.lokasi ?? '';
    formAgenda.publik = agenda.publik;
}

function simpanAgenda(): void {
    const opsi = { preserveScroll: true, onSuccess: () => resetAgenda() };

    if (suntingAgenda.value) {
        formAgenda.put(`/panel/organisasi/agenda/${suntingAgenda.value}`, opsi);
    } else {
        formAgenda.post('/panel/organisasi/agenda', opsi);
    }
}

function hapusAgenda(agenda: Agenda): void {
    if (!confirm(`Hapus agenda "${agenda.judul?.id}"?`)) {
        return;
    }

    router.delete(`/panel/organisasi/agenda/${agenda.id}`, { preserveScroll: true });
}

function saringUnit(id: number | ''): void {
    router.get('/panel/organisasi/galeri', id === '' ? {} : { unit: id }, { preserveScroll: false });
}
</script>

<template>
    <PanelLayout>
        <Head title="Galeri & Agenda Unit" />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Galeri & Agenda Unit</h1>
                    <p class="mt-1 text-sm text-muted">
                        Album foto dan agenda publik biro & LSO. Album boleh berdiri sendiri sebagai galeri rayon.
                    </p>
                </div>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Saring unit</span>
                    <select
                        :value="props.filterUnit ?? ''"
                        class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm font-bold"
                        @change="saringUnit(($event.target as HTMLSelectElement).value === '' ? '' : Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option value="">Semua unit</option>
                        <option v-for="unit in props.pilihanUnit" :key="unit.id" :value="unit.id">{{ unit.label }}</option>
                    </select>
                </label>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    :class="['brutal-sm px-4 py-2 text-sm font-bold', tab === 'album' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt']"
                    @click="tab = 'album'"
                >Album ({{ props.album.length }})</button>
                <button
                    type="button"
                    :class="['brutal-sm px-4 py-2 text-sm font-bold', tab === 'agenda' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt']"
                    @click="tab = 'agenda'"
                >Agenda ({{ props.agenda.length }})</button>
            </div>

            <!-- ============================ ALBUM ============================ -->
            <template v-if="tab === 'album'">
                <form class="brutal bg-paper p-5" @submit.prevent="simpanAlbum">
                    <h2 class="font-display text-lg">{{ suntingAlbum ? 'Sunting Album' : 'Buat Album' }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Indonesia) *</span>
                            <input v-model="formAlbum.judul.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAlbum.errors['judul.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ formAlbum.errors['judul.id'] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Inggris)</span>
                            <input v-model="formAlbum.judul.en" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Unit</span>
                            <select v-model="formAlbum.unit_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— galeri rayon —</option>
                                <option v-for="unit in props.pilihanUnit" :key="unit.id" :value="unit.id">{{ unit.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tanggal</span>
                            <input v-model="formAlbum.tanggal" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                            <input v-model="formAlbum.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Deskripsi (Indonesia)</span>
                            <textarea v-model="formAlbum.deskripsi.id" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                            <input v-model.number="formAlbum.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                    </div>

                    <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                        <input v-model="formAlbum.publik" type="checkbox" class="h-4 w-4"> Tampilkan di situs publik
                    </label>

                    <div class="mt-4 flex gap-3">
                        <button type="submit" :disabled="formAlbum.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                            {{ suntingAlbum ? 'Simpan Perubahan' : 'Buat Album' }}
                        </button>
                        <button v-if="suntingAlbum" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="resetAlbum">Batal</button>
                    </div>
                </form>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Album</h2>

                    <p v-if="!props.album.length" class="mt-3 text-sm text-muted">Belum ada album.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="album in props.album" :key="album.id" class="py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold">
                                        {{ album.judul?.id }}
                                        <span v-if="!album.publik" class="ml-2 text-xs font-normal text-muted">(tidak publik)</span>
                                    </p>
                                    <p class="text-xs text-muted">
                                        {{ album.unit ?? 'Galeri rayon' }}
                                        <template v-if="album.tanggal"> · {{ album.tanggal }}</template>
                                        · {{ album.jumlah_item }} foto
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="albumTerpilih = albumTerpilih?.id === album.id ? null : album">
                                        {{ albumTerpilih?.id === album.id ? 'Tutup Foto' : 'Kelola Foto' }}
                                    </button>
                                    <a v-if="album.tautan_publik" :href="album.tautan_publik" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">Lihat</a>
                                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="suntingAlbumMaju(album)">Sunting</button>
                                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapusAlbum(album)">Hapus</button>
                                </div>
                            </div>

                            <!-- Kelola foto album terpilih -->
                            <div v-if="albumTerpilih?.id === album.id" class="mt-4 border-t-2 border-ink/10 pt-4">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-xs font-bold uppercase text-muted">Pilih dari Pustaka Media</span>
                                        <select v-model="formFoto.media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                            <option :value="null">— pilih gambar —</option>
                                            <option v-for="gambar in props.pilihanGambar" :key="gambar.id" :value="gambar.id">{{ gambar.nama }}</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-bold uppercase text-muted">atau alamat gambar luar</span>
                                        <input v-model="formFoto.url" type="url" placeholder="https://…" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    </label>

                                    <label class="block sm:col-span-2">
                                        <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                                        <input v-model="formFoto.keterangan.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    </label>
                                </div>

                                <button type="button" :disabled="formFoto.processing" class="brutal-sm mt-3 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800" @click="tambahFoto">
                                    Tambah Foto
                                </button>

                                <p v-if="formFoto.errors.media_id || formFoto.errors.url" class="mt-2 text-xs font-bold text-accent-600">
                                    {{ formFoto.errors.media_id || formFoto.errors.url }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>

            <!-- ============================ AGENDA ============================ -->
            <template v-else>
                <form class="brutal bg-paper p-5" @submit.prevent="simpanAgenda">
                    <h2 class="font-display text-lg">{{ suntingAgenda ? 'Sunting Agenda' : 'Tambah Agenda' }}</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Indonesia) *</span>
                            <input v-model="formAgenda.judul.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAgenda.errors['judul.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors['judul.id'] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Inggris)</span>
                            <input v-model="formAgenda.judul.en" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Unit</span>
                            <select v-model="formAgenda.unit_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— agenda rayon —</option>
                                <option v-for="unit in props.pilihanUnit" :key="unit.id" :value="unit.id">{{ unit.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Mulai *</span>
                            <input v-model="formAgenda.mulai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAgenda.errors.mulai" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors.mulai }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Selesai</span>
                            <input v-model="formAgenda.selesai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAgenda.errors.selesai" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors.selesai }}</span>
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                            <input v-model="formAgenda.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Deskripsi</span>
                            <textarea v-model="formAgenda.deskripsi.id" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                        </label>
                    </div>

                    <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                        <input v-model="formAgenda.publik" type="checkbox" class="h-4 w-4"> Tampilkan di situs publik
                    </label>

                    <div class="mt-4 flex gap-3">
                        <button type="submit" :disabled="formAgenda.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                            {{ suntingAgenda ? 'Simpan Perubahan' : 'Tambah Agenda' }}
                        </button>
                        <button v-if="suntingAgenda" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="resetAgenda">Batal</button>
                    </div>
                </form>

                <section class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Agenda</h2>

                    <p v-if="!props.agenda.length" class="mt-3 text-sm text-muted">Belum ada agenda.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="agenda in props.agenda" :key="agenda.id" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ agenda.judul?.id }}
                                    <span v-if="!agenda.publik" class="ml-2 text-xs font-normal text-muted">(tidak publik)</span>
                                </p>
                                <p class="text-xs text-muted">
                                    {{ agenda.mulai?.replace('T', ' ') }}
                                    <template v-if="agenda.unit"> · {{ agenda.unit }}</template>
                                    <template v-if="agenda.lokasi"> · {{ agenda.lokasi }}</template>
                                    · {{ agenda.mendatang ? 'akan datang' : 'sudah lewat' }}
                                </p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="suntingAgendaMaju(agenda)">Sunting</button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapusAgenda(agenda)">Hapus</button>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </PanelLayout>
</template>
