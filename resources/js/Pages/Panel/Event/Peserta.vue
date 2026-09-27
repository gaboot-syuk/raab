<script setup lang="ts">
/*
 * Manajemen peserta event.
 *
 * Tindakan yang mengubah status diberi penjelasan pada tombolnya, karena
 * kesalahan di halaman ini berarti peserta menerima email yang salah.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Jawaban {
    label: string;
    nilai: string | null;
}

interface Peserta {
    id: number;
    kode_pendaftaran: string;
    nama_lengkap: string;
    email: string;
    telepon: string | null;
    jenis_kelamin: string | null;
    nim: string | null;
    fakultas: string | null;
    program_studi: string | null;
    angkatan: number | null;
    instansi: string | null;
    alamat: string | null;
    tempat_lahir: string | null;
    tanggal_lahir: string | null;
    status: string;
    label_status: string;
    hadir: boolean;
    catatan_peserta: string | null;
    catatan_panitia: string | null;
    dibuat_pada: string | null;
    member_id: number | null;
    nomor_anggota: string | null;
    boleh_dipromosikan: boolean;
    jawaban: Jawaban[];
}

const props = defineProps<{
    event: { id: number; judul: string; label_jenis: string; kuota: number | null; keadaan: Record<string, unknown> } | null;
    daftarEvent: { id: number; label: string }[];
    peserta: Peserta[];
    saring: { status: string; cari: string; hadir: string };
    status: Record<string, string>;
    ringkasan: Record<string, number> | null;
}>();

const cari = ref(props.saring.cari);
const status = ref(props.saring.status);
const hadir = ref(props.saring.hadir);
const terbuka = ref<number | null>(null);

function saringkan(): void {
    router.get('/panel/peserta', {
        event: props.event?.id,
        cari: cari.value,
        status: status.value,
        hadir: hadir.value,
    }, { preserveState: true, preserveScroll: true });
}

function reset(): void {
    cari.value = '';
    status.value = '';
    hadir.value = '';
    saringkan();
}

function gantiEvent(id: number): void {
    router.get('/panel/peserta', { event: id }, { preserveScroll: false });
}

function aksi(tautan: string, pesan?: string): void {
    if (pesan && !confirm(pesan)) {
        return;
    }

    router.post(tautan, {}, { preserveScroll: true });
}

function tolak(peserta: Peserta): void {
    bukaPanel('tolak', peserta);
}

function catatan(peserta: Peserta): void {
    bukaPanel('catatan', peserta);
}

/*
 * Alasan menolak dan catatan panitia diisi lewat panel kecil, bukan
 * window.prompt(). Prompt diblokir di konteks ber-sandbox — tombolnya jadi diam
 * saja — dan teksnya tidak pernah terlihat lagi setelah dikirim.
 */
type Panel = 'tolak' | 'catatan';

const panel = ref<Panel | null>(null);
const panelUntuk = ref<number | null>(null);
const formPanel = useForm({ catatan_panitia: '' });

function bukaPanel(macam: Panel, peserta: Peserta): void {
    if (panel.value === macam && panelUntuk.value === peserta.id) {
        panel.value = null;

        return;
    }

    panel.value = macam;
    panelUntuk.value = peserta.id;
    formPanel.reset();
    formPanel.clearErrors();
    formPanel.catatan_panitia = macam === 'catatan' ? (peserta.catatan_panitia ?? '') : '';
}

function kirimPanel(peserta: Peserta): void {
    formPanel.post(`/panel/peserta/${peserta.id}/${panel.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            panel.value = null;
            formPanel.reset();
        },
    });
}

function promosikan(peserta: Peserta): void {
    if (!confirm(`Jadikan ${peserta.nama_lengkap} sebagai Kader Aktif? Akun akan dibuat dan undangan dikirim ke ${peserta.email}.`)) {
        return;
    }

    router.post(`/panel/peserta/${peserta.id}/promosikan`, {}, { preserveScroll: true });
}

function warnaStatus(nilai: string): string {
    switch (nilai) {
        case 'hadir':
        case 'terverifikasi':
            return 'bg-success/30';
        case 'menunggu':
            return 'bg-accent-100';
        case 'ditolak':
        case 'batal':
            return 'bg-paper-alt';
        default:
            return 'bg-paper-alt';
    }
}
</script>

<template>
    <PanelLayout>
        <Head title="Peserta Event" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Peserta Event</h1>
                    <p class="mt-1 text-sm text-muted">
                        Verifikasi, catat kehadiran, dan promosikan peserta menjadi Kader Aktif tanpa input ulang data.
                    </p>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Event</span>
                        <select
                            :value="props.event?.id"
                            class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm font-bold"
                            @change="gantiEvent(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="item in props.daftarEvent" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                    </label>

                    <Link href="/panel/event" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold">← Event</Link>
                </div>
            </div>

            <p v-if="!props.event" class="brutal bg-paper-alt p-6 text-sm text-muted">
                Belum ada event. Buat dulu di <Link href="/panel/event" class="font-bold underline">halaman Event</Link>.
            </p>

            <template v-else>
                <!-- Ringkasan -->
                <ul v-if="props.ringkasan" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.total }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Total</p>
                    </li>
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.menunggu }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Menunggu</p>
                    </li>
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.terverifikasi }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Terverifikasi</p>
                    </li>
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.hadir }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Hadir</p>
                    </li>
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.ditolak }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Ditolak</p>
                    </li>
                    <li class="brutal bg-paper p-4">
                        <p class="font-display text-2xl">{{ props.ringkasan.siap_dipromosikan }}</p>
                        <p class="text-xs font-bold uppercase text-muted">Siap Dipromosikan</p>
                    </li>
                </ul>

                <!-- Saringan -->
                <form class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-4" @submit.prevent="saringkan">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Cari</span>
                        <input v-model="cari" type="search" placeholder="nama, email, kode, atau telepon" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Status</span>
                        <select v-model="status" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option value="">Semua status</option>
                            <option v-for="(label, nilai) in props.status" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Kehadiran</span>
                        <select v-model="hadir" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            <option value="1">Sudah hadir</option>
                            <option value="0">Belum hadir</option>
                        </select>
                    </label>

                    <div class="flex flex-wrap items-end gap-2 sm:col-span-4">
                        <button type="submit" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">Terapkan</button>
                        <button type="button" class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold" @click="reset">Reset</button>

                        <a
                            :href="`/panel/event/${props.event.id}/ekspor?status=${status}&hadir=${hadir}&cari=${encodeURIComponent(cari)}`"
                            class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold"
                        >Ekspor CSV ({{ props.peserta.length }})</a>
                    </div>
                </form>

                <!-- Daftar peserta -->
                <p v-if="!props.peserta.length" class="brutal bg-paper-alt p-8 text-center text-sm text-muted">
                    Belum ada peserta yang cocok dengan saringan ini.
                </p>

                <ul v-else class="space-y-3">
                    <li v-for="orang in props.peserta" :key="orang.id" class="brutal bg-paper p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ orang.nama_lengkap }}
                                    <span :class="['border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase', warnaStatus(orang.status)]">
                                        {{ orang.label_status }}
                                    </span>
                                    <span v-if="orang.hadir" class="ml-1 border-2 border-ink bg-success/30 px-2 py-0.5 text-[10px] font-bold uppercase">Hadir</span>
                                </p>
                                <p class="mt-1 text-xs text-muted">
                                    <span class="font-mono">{{ orang.kode_pendaftaran }}</span>
                                    · {{ orang.email }}
                                    <template v-if="orang.telepon"> · {{ orang.telepon }}</template>
                                </p>
                                <p class="text-xs text-muted">
                                    <template v-if="orang.instansi">{{ orang.instansi }}</template>
                                    <template v-if="orang.program_studi"> · {{ orang.program_studi }}</template>
                                    <template v-if="orang.angkatan"> · {{ orang.angkatan }}</template>
                                    · daftar {{ orang.dibuat_pada }}
                                </p>
                                <p v-if="orang.nomor_anggota" class="mt-1 text-xs font-bold text-success">
                                    Kader Aktif · {{ orang.nomor_anggota }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-if="orang.status === 'menunggu'"
                                    type="button"
                                    class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                                    @click="aksi(`/panel/peserta/${orang.id}/verifikasi`)"
                                >Verifikasi</button>

                                <button
                                    v-if="orang.status !== 'ditolak' && orang.status !== 'batal'"
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                    @click="tolak(orang)"
                                >Tolak</button>

                                <button
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                                    @click="aksi(`/panel/peserta/${orang.id}/hadir`, orang.hadir ? `Batalkan tanda hadir ${orang.nama_lengkap}?` : null)"
                                >{{ orang.hadir ? 'Batal Hadir' : 'Tandai Hadir' }}</button>

                                <button
                                    v-if="orang.boleh_dipromosikan"
                                    type="button"
                                    class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                                    @click="promosikan(orang)"
                                >Jadikan Kader Aktif</button>

                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="catatan(orang)">Catatan</button>

                                <a :href="`/panel/peserta/${orang.id}/kartu`" target="_blank" rel="noopener" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">Kartu</a>

                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="terbuka = terbuka === orang.id ? null : orang.id">
                                    {{ terbuka === orang.id ? 'Tutup' : 'Detail' }}
                                </button>
                            </div>
                        </div>

                        <p v-if="orang.catatan_panitia" class="mt-2 text-xs">
                            <span class="font-bold uppercase">Catatan panitia:</span> {{ orang.catatan_panitia }}
                        </p>

                        <form
                            v-if="panelUntuk === orang.id"
                            class="mt-3 border-t-2 border-ink/10 pt-3"
                            @submit.prevent="kirimPanel(orang)"
                        >
                            <label class="block">
                                <span class="text-xs font-bold uppercase text-muted">
                                    {{ panel === 'tolak' ? `Alasan menolak ${orang.nama_lengkap}` : `Catatan panitia untuk ${orang.nama_lengkap}` }}
                                </span>
                                <textarea v-model="formPanel.catatan_panitia" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                                <span v-if="formPanel.errors.catatan_panitia" class="mt-1 block text-xs font-bold text-accent-600">{{ formPanel.errors.catatan_panitia }}</span>
                            </label>

                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="submit" :disabled="formPanel.processing" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800">
                                    {{ panel === 'tolak' ? 'Kirim Penolakan' : 'Simpan Catatan' }}
                                </button>
                                <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="panel = null">Batal</button>
                            </div>
                        </form>

                        <!-- Detail -->
                        <dl v-if="terbuka === orang.id" class="mt-4 grid gap-3 border-t-2 border-ink/10 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">Jenis Kelamin</dt>
                                <dd>{{ orang.jenis_kelamin ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">Tempat / Tanggal Lahir</dt>
                                <dd>{{ orang.tempat_lahir ?? '—' }}<template v-if="orang.tanggal_lahir">, {{ orang.tanggal_lahir }}</template></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">NIM</dt>
                                <dd>{{ orang.nim ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-bold uppercase text-muted">Fakultas</dt>
                                <dd>{{ orang.fakultas ?? '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-bold uppercase text-muted">Alamat</dt>
                                <dd>{{ orang.alamat ?? '—' }}</dd>
                            </div>
                            <div v-if="orang.catatan_peserta" class="sm:col-span-2 lg:col-span-3">
                                <dt class="text-xs font-bold uppercase text-muted">Catatan dari Peserta</dt>
                                <dd>{{ orang.catatan_peserta }}</dd>
                            </div>

                            <div v-for="jawaban in orang.jawaban" :key="jawaban.label">
                                <dt class="text-xs font-bold uppercase text-muted">{{ jawaban.label }}</dt>
                                <dd>{{ jawaban.nilai === '1' ? 'Ya' : (jawaban.nilai === '0' ? 'Tidak' : (jawaban.nilai ?? '—')) }}</dd>
                            </div>
                        </dl>
                    </li>
                </ul>
            </template>
        </div>
    </PanelLayout>
</template>
