<script setup lang="ts">
/*
 * Kegiatan yang presensinya dicatat.
 *
 * TOMBOL "BUKA" ADALAH PINTU MASUKNYA. Sebelum dibuka, tidak ada kode QR dan
 * tidak ada satu pun kehadiran yang bisa dicatat — baik oleh panitia maupun
 * oleh kader. Itu disengaja: presensi tidak boleh berjalan tanpa ada yang
 * memutuskan "acara ini dimulai".
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Kegiatan {
    id: number;
    kode: string;
    judul: string;
    jenis: string;
    label_jenis: string;
    status: string;
    label_status: string;
    mode_presensi: string;
    label_mode: string;
    unit: string | null;
    mulai: string | null;
    lokasi: string | null;
    poin: number;
    wajib: boolean;
    total_hadir: number;
    total_catatan: number;
    total_rsvp: number;
    qr_aktif: boolean;
    qr_berlaku_sampai: string | null;
    tautan_qr: string | null;
}

const props = defineProps<{
    daftar: Kegiatan[];
    pilihanJenis: Record<string, string>;
    pilihanMode: Record<string, string>;
    pilihanStatus: Record<string, string>;
    pilihanUnit: { id: number; nama: string }[];
    saringan: { jenis: string; status: string };
    catatan: string;
}>();

const bukaForm = ref(false);
const batalUntuk = ref<number | null>(null);
const formBatal = useForm({ alasan: '' });

const jenisSaring = ref(props.saringan.jenis);
const statusSaring = ref(props.saringan.status);

const form = useForm({
    judul: '',
    deskripsi: '',
    jenis: 'rapat',
    unit_id: null as number | null,
    mulai: '',
    selesai: '',
    lokasi: '',
    mode_presensi: 'keduanya',
    poin: 0,
    wajib: false,
});

function saring(): void {
    router.get('/panel/kegiatan', { jenis: jenisSaring.value, status: statusSaring.value }, { preserveScroll: true });
}

function simpan(): void {
    form.post('/panel/kegiatan', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            bukaForm.value = false;
        },
    });
}

function aksi(tautan: string, pesan: string): void {
    if (!confirm(pesan)) {
        return;
    }

    router.post(tautan, {}, { preserveScroll: true });
}

function kirimBatal(k: Kegiatan): void {
    formBatal.post(`/panel/kegiatan/${k.id}/batalkan`, {
        preserveScroll: true,
        onSuccess: () => {
            batalUntuk.value = null;
            formBatal.reset();
        },
    });
}
</script>

<template>
    <Head title="Kegiatan" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Kegiatan</h1>
                    <p class="text-sm text-muted">Agenda yang kehadirannya dicatat, beserta kode QR presensinya.</p>
                </div>

                <button
                    type="button"
                    class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    @click="bukaForm = !bukaForm"
                >
                    {{ bukaForm ? 'Tutup Formulir' : 'Kegiatan Baru' }}
                </button>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed text-ink">{{ props.catatan }}</p>

            <!-- ===== Formulir kegiatan baru ===== -->
            <form v-if="bukaForm" class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">Kegiatan Baru</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Judul kegiatan *</span>
                        <input v-model="form.judul" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.judul" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.judul }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                        <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, kunci) in props.pilihanJenis" :key="kunci" :value="kunci">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Biro &amp; LSO</span>
                        <select v-model="form.unit_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— Tingkat rayon —</option>
                            <option v-for="u in props.pilihanUnit" :key="u.id" :value="u.id">{{ u.nama }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Mulai *</span>
                        <input v-model="form.mulai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.mulai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.mulai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Selesai</span>
                        <input v-model="form.selesai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.selesai" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.selesai }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                        <input v-model="form.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Mode presensi *</span>
                        <select v-model="form.mode_presensi" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, kunci) in props.pilihanMode" :key="kunci" :value="kunci">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Poin kehadiran</span>
                        <input v-model.number="form.poin" type="number" min="0" max="50" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span class="text-[11px] text-muted">Diberikan penuh hanya bila tercatat Hadir atau Terlambat.</span>
                        <span v-if="form.errors.poin" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.poin }}</span>
                    </label>

                    <label class="flex items-center gap-2 sm:col-span-2">
                        <input v-model="form.wajib" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        <span class="text-sm">Kegiatan wajib — ketidakhadiran tanpa keterangan perlu ditindaklanjuti</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi</span>
                        <textarea v-model="form.deskripsi" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                    </label>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" :disabled="form.processing" class="brutal-sm bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 disabled:opacity-40">
                        Simpan sebagai Draf
                    </button>
                    <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="bukaForm = false">Batal</button>
                </div>
            </form>

            <!-- ===== Saringan ===== -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Jenis</span>
                    <select v-model="jenisSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua jenis</option>
                        <option v-for="(label, kunci) in props.pilihanJenis" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Status</span>
                    <select v-model="statusSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua status</option>
                        <option v-for="(label, kunci) in props.pilihanStatus" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>
            </div>

            <p v-if="!props.daftar.length" class="brutal bg-paper p-5 text-sm text-muted">
                Belum ada kegiatan yang cocok dengan saringan ini.
            </p>

            <!-- ===== Daftar kegiatan ===== -->
            <ul v-else class="space-y-4">
                <li v-for="k in props.daftar" :key="k.id" class="brutal bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-muted">{{ k.kode }} · {{ k.label_jenis }}</p>
                            <h2 class="font-display text-lg leading-tight">{{ k.judul }}</h2>
                            <p class="mt-1 text-sm text-muted">
                                {{ k.mulai }} WIB
                                <span v-if="k.lokasi"> · {{ k.lokasi }}</span>
                                <span v-if="k.unit"> · {{ k.unit }}</span>
                            </p>

                            <p class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                                <span class="border-2 border-ink px-2 py-0.5" :class="k.status === 'terbuka' ? 'bg-accent-100' : 'bg-paper-alt'">
                                    {{ k.label_status }}
                                </span>
                                <span class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ k.label_mode }}</span>
                                <span v-if="k.wajib" class="border-2 border-ink bg-accent-400 px-2 py-0.5">Wajib</span>
                                <span v-if="k.poin > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ k.poin }} poin</span>
                            </p>
                        </div>

                        <div class="text-right text-sm">
                            <p class="font-display text-2xl">{{ k.total_hadir }}</p>
                            <p class="text-[10px] font-bold uppercase text-muted">Hadir dari {{ k.total_catatan }} catatan</p>
                            <p class="text-[10px] text-muted">{{ k.total_rsvp }} menyatakan akan hadir</p>
                        </div>
                    </div>

                    <p v-if="k.qr_aktif" class="mt-3 border-2 border-ink bg-accent-100 p-3 text-xs">
                        Kode QR aktif sampai <strong>{{ k.qr_berlaku_sampai }}</strong>.
                        <span class="mt-1 block break-all font-mono text-[10px]">{{ k.tautan_qr }}</span>
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="k.status !== 'terbuka' && k.status !== 'batal' && k.status !== 'selesai'"
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                            @click="aksi(`/panel/kegiatan/${k.id}/buka`, `Buka presensi ${k.judul}? Kode QR baru akan diterbitkan.`)"
                        >
                            Buka Presensi
                        </button>

                        <a
                            v-if="k.qr_aktif"
                            :href="`/panel/kegiatan/${k.id}/qr`"
                            target="_blank"
                            rel="noopener"
                            class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold"
                        >Tampilkan QR</a>

                        <button
                            v-if="k.qr_aktif"
                            type="button"
                            class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold"
                            @click="aksi(`/panel/kegiatan/${k.id}/putar-qr`, 'Putar kode QR? Tautan lama langsung tidak berlaku.')"
                        >
                            Putar QR
                        </button>

                        <a :href="`/panel/presensi?kegiatan=${k.id}`" class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold">
                            Catat Kehadiran
                        </a>

                        <button
                            v-if="k.status === 'terbuka'"
                            type="button"
                            class="brutal-sm brutal-hover bg-paper px-3 py-1.5 text-xs font-bold"
                            @click="aksi(`/panel/kegiatan/${k.id}/tutup`, 'Tutup presensi? Kehadiran tidak dapat dicatat lagi dan rekapnya dianggap final.')"
                        >
                            Tutup Presensi
                        </button>

                        <button
                            v-if="k.status !== 'batal'"
                            type="button"
                            class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                            @click="batalUntuk = batalUntuk === k.id ? null : k.id"
                        >
                            {{ batalUntuk === k.id ? 'Tutup' : 'Batalkan' }}
                        </button>
                    </div>

                    <form v-if="batalUntuk === k.id" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimBatal(k)">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan pembatalan *</span>
                            <textarea v-model="formBatal.alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                            <span class="text-[11px] text-muted">Kegiatan tidak dihapus — kehadiran yang sudah tercatat tetap tersimpan.</span>
                            <span v-if="formBatal.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formBatal.errors.alasan }}</span>
                        </label>

                        <div class="mt-2 flex gap-2">
                            <button type="submit" :disabled="formBatal.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Batalkan Kegiatan</button>
                            <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="batalUntuk = null">Tutup</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </PanelLayout>
</template>
