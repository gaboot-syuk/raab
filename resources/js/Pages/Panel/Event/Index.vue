<script setup lang="ts">
/*
 * Event Mapaba & PKD — CRUD event beserta kolom isian tambahannya.
 *
 * Kuota sengaja boleh dikosongkan: kosong berarti TIDAK TERBATAS. Dibedakan
 * jelas dari 0 yang berarti tertutup.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { useGulirKeForm } from '@/composables/useGulirKeForm';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Kolom {
    id: number;
    kunci: string;
    label: Record<string, string> | null;
    tipe: string;
    pilihan: Record<string, string[]> | string[] | null;
    wajib: boolean;
    urutan: number;
    aktif: boolean;
    jumlah_jawaban: number;
}

interface Keadaan {
    dibuka: boolean;
    alasan: string | null;
    terisi: number;
    kuota: number | null;
    sisa: number | null;
}

interface Event {
    id: number;
    jenis: string;
    label_jenis: string;
    judul: Record<string, string> | null;
    slug: string | null;
    deskripsi: Record<string, string> | null;
    syarat: Record<string, string> | null;
    poster_media_id: number | null;
    poster: string | null;
    kuota: number | null;
    biaya: number;
    lokasi: string | null;
    mulai: string | null;
    selesai: string | null;
    pendaftaran_dibuka: string | null;
    pendaftaran_ditutup: string | null;
    aktif: boolean;
    urutan: number;
    jumlah_pendaftar: number;
    terisi: number;
    sisa: number | null;
    keadaan: Keadaan;
    kolom: Kolom[];
}

const props = defineProps<{
    daftar: Event[];
    jenis: Record<string, string>;
    tipeKolom: Record<string, string>;
    pilihanPoster: { id: number; nama: string; url: string }[];
}>();

const sunting = ref<number | null>(null);
const { wadah, gulirKeForm } = useGulirKeForm();
const kelolaKolom = ref<number | null>(null);
const suntingKolom = ref<number | null>(null);

const form = useForm({
    jenis: 'mapaba',
    judul: { id: '', en: '' } as Record<string, string>,
    deskripsi: { id: '', en: '' } as Record<string, string>,
    syarat: { id: '', en: '' } as Record<string, string>,
    poster_media_id: null as number | null,
    kuota: null as number | null,
    biaya: 0,
    pendaftaran_dibuka: '',
    pendaftaran_ditutup: '',
    mulai: '',
    selesai: '',
    lokasi: '',
    urutan: 0,
    aktif: true,
});

const formKolom = useForm({
    kunci: '',
    label: { id: '', en: '' } as Record<string, string>,
    tipe: 'teks',
    pilihan: '' as string,
    wajib: false,
    urutan: 0,
    aktif: true,
});

function reset(): void {
    sunting.value = null;
    form.reset();
    form.clearErrors();
}

function mulaiSunting(event: Event): void {
    sunting.value = event.id;
    form.clearErrors();
    form.jenis = event.jenis;
    form.judul = { id: event.judul?.id ?? '', en: event.judul?.en ?? '' };
    form.deskripsi = { id: event.deskripsi?.id ?? '', en: event.deskripsi?.en ?? '' };
    form.syarat = { id: event.syarat?.id ?? '', en: event.syarat?.en ?? '' };
    form.poster_media_id = event.poster_media_id;
    form.kuota = event.kuota;
    form.biaya = event.biaya;
    form.pendaftaran_dibuka = event.pendaftaran_dibuka ?? '';
    form.pendaftaran_ditutup = event.pendaftaran_ditutup ?? '';
    form.mulai = event.mulai ?? '';
    form.selesai = event.selesai ?? '';
    form.lokasi = event.lokasi ?? '';
    form.urutan = event.urutan;
    form.aktif = event.aktif;

    gulirKeForm();
}

function simpan(): void {
    const opsi = { preserveScroll: true, onSuccess: () => reset() };

    if (sunting.value) {
        form.put(`/panel/event/${sunting.value}`, opsi);
    } else {
        form.post('/panel/event', opsi);
    }
}

function hapus(event: Event): void {
    if (!confirm(`Hapus event "${event.judul?.id}"?`)) {
        return;
    }

    router.delete(`/panel/event/${event.id}`, { preserveScroll: true });
}

function resetKolom(): void {
    suntingKolom.value = null;
    formKolom.reset();
    formKolom.clearErrors();
    formKolom.pilihan = '';
}

function mulaiSuntingKolom(kolom: Kolom): void {
    suntingKolom.value = kolom.id;
    formKolom.clearErrors();
    formKolom.kunci = kolom.kunci;
    formKolom.label = { id: kolom.label?.id ?? '', en: kolom.label?.en ?? '' };
    formKolom.tipe = kolom.tipe;
    formKolom.pilihan = Array.isArray(kolom.pilihan)
        ? kolom.pilihan.join('\n')
        : '';
    formKolom.wajib = kolom.wajib;
    formKolom.urutan = kolom.urutan;
    formKolom.aktif = kolom.aktif;
}

function simpanKolom(event: Event): void {
    const opsi = { preserveScroll: true, onSuccess: () => resetKolom() };

    if (suntingKolom.value) {
        formKolom.put(`/panel/event/kolom/${suntingKolom.value}`, opsi);
    } else {
        formKolom.post(`/panel/event/${event.id}/kolom`, opsi);
    }
}

function hapusKolom(kolom: Kolom): void {
    if (!confirm(`Hapus kolom "${kolom.label?.id}"?`)) {
        return;
    }

    router.delete(`/panel/event/kolom/${kolom.id}`, { preserveScroll: true });
}

function ringkasKeadaan(k: Keadaan): string {
    if (!k.dibuka) {
        return k.alasan ?? 'Pendaftaran tertutup';
    }

    if (k.sisa === null) {
        return `Dibuka · ${k.terisi} pendaftar · kuota tidak dibatasi`;
    }

    return `Dibuka · ${k.terisi}/${k.kuota} kursi terisi · sisa ${k.sisa}`;
}
</script>

<template>
    <PanelLayout>
        <Head title="Event Mapaba & PKD" />

        <PesanHasil />

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Event Mapaba & PKD</h1>
                    <p class="mt-1 text-sm text-muted">
                        Pendaftaran dibuka & ditutup otomatis mengikuti tanggal dan kuota — tidak perlu dipantau manual.
                    </p>
                </div>

                <Link href="/panel/peserta" class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold">
                    Manajemen Peserta →
                </Link>
            </div>

            <!-- Formulir -->
            <form ref="wadah" class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">{{ sunting ? 'Sunting Event' : 'Buat Event' }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                        <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in props.jenis" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Judul (Indonesia) *</span>
                        <input v-model="form.judul.id" type="text" placeholder="Mapaba 2026" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors['judul.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors['judul.id'] }}</span>
                    </label>

                    <label class="block sm:col-span-2 lg:col-span-1">
                        <span class="text-xs font-bold uppercase text-muted">Judul (Inggris)</span>
                        <input v-model="form.judul.en" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kuota</span>
                        <input v-model.number="form.kuota" type="number" min="0" placeholder="kosong = tidak dibatasi" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kontribusi (Rp)</span>
                        <input v-model.number="form.biaya" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Pendaftaran dibuka</span>
                        <input v-model="form.pendaftaran_dibuka" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Pendaftaran ditutup</span>
                        <input v-model="form.pendaftaran_ditutup" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.pendaftaran_ditutup" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.pendaftaran_ditutup }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                        <input v-model="form.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Mulai</span>
                        <input v-model="form.mulai" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Selesai</span>
                        <input v-model="form.selesai" type="date" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.selesai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.selesai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Poster</span>
                        <select v-model="form.poster_media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— tanpa poster —</option>
                            <option v-for="gambar in props.pilihanPoster" :key="gambar.id" :value="gambar.id">{{ gambar.nama }}</option>
                        </select>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Indonesia)</span>
                        <textarea v-model="form.deskripsi.id" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Inggris)</span>
                        <textarea v-model="form.deskripsi.en" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>

                    <label class="block sm:col-span-2 lg:col-span-3">
                        <span class="text-xs font-bold uppercase text-muted">Syarat pendaftaran (satu baris satu syarat)</span>
                        <textarea v-model="form.syarat.id" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                    <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Aktif (tampil di situs)
                </label>

                <div class="mt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        {{ sunting ? 'Simpan Perubahan' : 'Buat Event' }}
                    </button>
                    <button v-if="sunting" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="reset">Batal</button>
                </div>
            </form>

            <!-- Daftar event -->
            <section class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Daftar Event</h2>

                <p v-if="!props.daftar.length" class="mt-3 text-sm text-muted">Belum ada event.</p>

                <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="event in props.daftar" :key="event.id" class="py-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ event.judul?.id }}
                                    <span class="brutal-sm ml-2 bg-paper-alt px-2 py-0.5 text-[10px] uppercase">{{ event.label_jenis }}</span>
                                    <span v-if="!event.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                                </p>
                                <p class="text-xs text-muted">{{ ringkasKeadaan(event.keadaan) }}</p>
                                <p class="text-xs text-muted">
                                    {{ event.mulai ?? 'tanggal belum diisi' }}
                                    <template v-if="event.lokasi"> · {{ event.lokasi }}</template>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="kelolaKolom = kelolaKolom === event.id ? null : event.id">
                                    {{ kelolaKolom === event.id ? 'Tutup Kolom' : `Kolom (${event.kolom.length})` }}
                                </button>
                                <a v-if="event.slug" :href="`/pendaftaran/${event.slug}`" target="_blank" rel="noopener noreferrer" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">Lihat</a>
                                <Link :href="`/panel/peserta?event=${event.id}`" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">Peserta ({{ event.jumlah_pendaftar }})</Link>
                                <a :href="`/panel/event/${event.id}/ekspor`" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">Ekspor</a>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="mulaiSunting(event)">Sunting</button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapus(event)">Hapus</button>
                            </div>
                        </div>

                        <!-- Kelola kolom tambahan -->
                        <div v-if="kelolaKolom === event.id" class="mt-4 border-t-2 border-ink/10 pt-4">
                            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="simpanKolom(event)">
                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Nama kolom (tanpa spasi)</span>
                                    <input v-model="formKolom.kunci" type="text" placeholder="ukuran_kaos" :disabled="suntingKolom !== null" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm disabled:opacity-60">
                                    <span v-if="formKolom.errors.kunci" class="mt-1 block text-xs font-bold text-accent-600">{{ formKolom.errors.kunci }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Label (Indonesia) *</span>
                                    <input v-model="formKolom.label.id" type="text" placeholder="Ukuran Kaos" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                    <span v-if="formKolom.errors['label.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ formKolom.errors['label.id'] }}</span>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Tipe</span>
                                    <select v-model="formKolom.tipe" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                        <option v-for="(label, nilai) in props.tipeKolom" :key="nilai" :value="nilai">{{ label }}</option>
                                    </select>
                                </label>

                                <label v-if="formKolom.tipe === 'pilihan'" class="block sm:col-span-2 lg:col-span-3">
                                    <span class="text-xs font-bold uppercase text-muted">Pilihan (satu per baris)</span>
                                    <textarea v-model="formKolom.pilihan" rows="3" placeholder="S&#10;M&#10;L&#10;XL" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                                </label>

                                <label class="block">
                                    <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                                    <input v-model.number="formKolom.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                </label>

                                <div class="flex items-end gap-4">
                                    <label class="flex items-center gap-2 text-sm font-bold">
                                        <input v-model="formKolom.wajib" type="checkbox" class="h-4 w-4"> Wajib diisi
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-bold">
                                        <input v-model="formKolom.aktif" type="checkbox" class="h-4 w-4"> Aktif
                                    </label>
                                </div>

                                <div class="flex items-end gap-2">
                                    <button type="submit" :disabled="formKolom.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                                        {{ suntingKolom ? 'Simpan' : 'Tambah Kolom' }}
                                    </button>
                                    <button v-if="suntingKolom" type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="resetKolom">Batal</button>
                                </div>
                            </form>

                            <p v-if="!event.kolom.length" class="mt-3 text-sm text-muted">Belum ada kolom tambahan.</p>

                            <table v-else class="mt-3 w-full text-sm">
                                <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                                    <tr>
                                        <th class="py-2">Label</th>
                                        <th class="py-2">Nama kolom</th>
                                        <th class="py-2">Tipe</th>
                                        <th class="py-2 text-right">Jawaban</th>
                                        <th class="py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="kolom in event.kolom" :key="kolom.id" class="border-b-2 border-ink/10">
                                        <td class="py-2 font-bold">{{ kolom.label?.id }}</td>
                                        <td class="py-2 font-mono text-xs">{{ kolom.kunci }}</td>
                                        <td class="py-2">{{ props.tipeKolom[kolom.tipe] }}</td>
                                        <td class="py-2 text-right">{{ kolom.jumlah_jawaban }}</td>
                                        <td class="py-2">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="mulaiSuntingKolom(kolom)">Sunting</button>
                                                <button type="button" class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold" @click="hapusKolom(kolom)">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
