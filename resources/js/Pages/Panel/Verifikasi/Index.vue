<script setup lang="ts">
/*
 * Antrean verifikasi pendaftar (Sekretaris).
 * Buka satu pengajuan untuk membaca seluruh isian, lalu putuskan:
 * setujui / minta perbaikan / tolak. Ketiganya wajib berbekal catatan
 * (kecuali persetujuan) agar pemohon tahu alasannya.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Pengajuan {
    id: number;
    jalur: string;
    label_jalur: string;
    status: string;
    label_status: string;
    nama: string;
    email: string | null;
    telepon: string | null;
    nim: string | null;
    fakultas: string | null;
    program_studi: string | null;
    angkatan: number | null;
    tahun_lulus: number | null;
    instansi: string | null;
    unit: string | null;
    dikirim_pada: string;
}

const props = defineProps<{
    daftar: {
        data: Pengajuan[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    saring: string;
    cari: string;
    jumlah: { menunggu: number; perbaikan: number; disetujui: number; ditolak: number };
    pilihanStatus: Record<string, string>;
}>();

const terpilihId = ref<number | null>(props.daftar.data[0]?.id ?? null);
const cariLokal = ref(props.cari);

const terpilih = computed<Pengajuan | null>(
    () => props.daftar.data.find((p) => p.id === terpilihId.value) ?? null,
);

const tab = computed(() => [
    { nilai: 'menunggu', label: 'Menunggu', jumlah: props.jumlah.menunggu },
    { nilai: 'perbaikan', label: 'Perlu Perbaikan', jumlah: props.jumlah.perbaikan },
    { nilai: 'disetujui', label: 'Disetujui', jumlah: props.jumlah.disetujui },
    { nilai: 'ditolak', label: 'Ditolak', jumlah: props.jumlah.ditolak },
]);

const formCatatan = useForm({ catatan_pengurus: '' });
const modal = ref<'tolak' | 'perbaikan' | null>(null);
const konfirmasiSetujui = ref(false);

function saringKe(nilai: string) {
    router.get('/panel/verifikasi', nilai === 'menunggu' ? {} : { status: nilai }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function cari() {
    router.get('/panel/verifikasi', { cari: cariLokal.value, status: props.saring }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setujui(pengajuan: Pengajuan) {
    router.post(`/panel/verifikasi/${pengajuan.id}/setujui`, {}, { preserveScroll: true });
    konfirmasiSetujui.value = false;
}

function kirimCatatan() {
    if (!terpilih.value || !modal.value) {
        return;
    }

    const aksi = modal.value === 'tolak' ? 'tolak' : 'perbaikan';

    formCatatan.post(`/panel/verifikasi/${terpilih.value.id}/${aksi}`, {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = null;
            formCatatan.reset();
        },
    });
}

// Bila daftar berubah halaman/filter, pilih baris pertama yang tersedia.
watch(
    () => props.daftar.data,
    (baru) => {
        if (!baru.some((p) => p.id === terpilihId.value)) {
            terpilihId.value = baru[0]?.id ?? null;
        }
    },
);
</script>

<template>
    <PanelLayout>
        <Head title="Verifikasi Anggota" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Verifikasi Anggota</h1>
                <p class="mt-1 text-sm text-muted">
                    Pengajuan keanggotaan yang menunggu keputusan Sekretaris.
                    Nomor anggota diterbitkan saat pengajuan disetujui.
                </p>
            </div>

            <PesanHasil />

            <p class="brutal-sm border-ink bg-accent-100 px-4 py-3 text-sm">
                Kamu tidak dapat memverifikasi pengajuanmu sendiri — pengajuan seperti itu akan ditolak sistem.
            </p>

            <!-- Saringan -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="t in tab"
                    :key="t.nilai"
                    type="button"
                    class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                    :class="saring === t.nilai ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                    @click="saringKe(t.nilai)"
                >
                    {{ t.label }}
                    <span class="ml-1 border-2 border-ink bg-paper px-1 text-[11px]">{{ t.jumlah }}</span>
                </button>

                <form class="ml-auto flex gap-2" @submit.prevent="cari">
                    <input
                        v-model="cariLokal"
                        type="search"
                        placeholder="Cari nama, email, atau NIM…"
                        class="brutal-sm w-56 bg-paper px-3 py-1.5 text-sm"
                    >
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-3 py-1.5 text-sm font-bold text-paper">Cari</button>
                </form>
            </div>

            <div v-if="!daftar.data.length" class="brutal bg-paper-alt px-4 py-10 text-center text-sm text-muted">
                Tidak ada pengajuan pada kategori ini.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-[22rem_1fr]">
                <!-- Daftar -->
                <div class="space-y-2">
                    <button
                        v-for="p in daftar.data"
                        :key="p.id"
                        type="button"
                        class="w-full border-2 border-ink p-3 text-left"
                        :class="terpilihId === p.id ? 'bg-accent-400' : 'bg-paper hover:bg-accent-100'"
                        @click="terpilihId = p.id"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate font-bold">{{ p.nama }}</span>
                            <span class="shrink-0 border-2 border-ink bg-paper-alt px-1.5 text-[10px] font-bold uppercase">
                                {{ p.label_jalur }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs text-muted">{{ p.email }}</p>
                        <p class="mt-1 text-xs text-muted">{{ p.dikirim_pada }}</p>
                    </button>

                    <Penomoran :tautan="daftar.links" />
                </div>

                <!-- Detail & keputusan -->
                <div v-if="terpilih" class="brutal h-fit bg-paper p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b-2 border-ink pb-4">
                        <div>
                            <h2 class="font-display text-lg">{{ terpilih.nama }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ terpilih.email }}</p>
                        </div>
                        <span class="border-2 border-ink bg-paper-alt px-2 py-1 text-xs font-bold uppercase">
                            {{ terpilih.label_status }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">Jalur</dt>
                            <dd>{{ terpilih.label_jalur }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-bold uppercase text-muted">Telepon</dt>
                            <dd>{{ terpilih.telepon || '—' }}</dd>
                        </div>
                        <div v-if="terpilih.nim">
                            <dt class="text-xs font-bold uppercase text-muted">NIM</dt>
                            <dd>{{ terpilih.nim }}</dd>
                        </div>
                        <div v-if="terpilih.angkatan">
                            <dt class="text-xs font-bold uppercase text-muted">Angkatan</dt>
                            <dd>{{ terpilih.angkatan }}</dd>
                        </div>
                        <div v-if="terpilih.fakultas">
                            <dt class="text-xs font-bold uppercase text-muted">Fakultas</dt>
                            <dd>{{ terpilih.fakultas }}</dd>
                        </div>
                        <div v-if="terpilih.program_studi">
                            <dt class="text-xs font-bold uppercase text-muted">Program Studi</dt>
                            <dd>{{ terpilih.program_studi }}</dd>
                        </div>
                        <div v-if="terpilih.tahun_lulus">
                            <dt class="text-xs font-bold uppercase text-muted">Tahun Lulus</dt>
                            <dd>{{ terpilih.tahun_lulus }}</dd>
                        </div>
                        <div v-if="terpilih.instansi">
                            <dt class="text-xs font-bold uppercase text-muted">Instansi</dt>
                            <dd>{{ terpilih.instansi }}</dd>
                        </div>
                        <div v-if="terpilih.unit">
                            <dt class="text-xs font-bold uppercase text-muted">Biro / LSO Diminati</dt>
                            <dd>{{ terpilih.unit }}</dd>
                        </div>
                    </dl>

                    <div v-if="terpilih.status === 'menunggu'" class="mt-6 flex flex-wrap gap-2 border-t-2 border-ink pt-4">
                        <button
                            type="button"
                            class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper"
                            @click="konfirmasiSetujui = true"
                        >Setujui</button>
                        <button
                            type="button"
                            class="brutal-sm brutal-hover bg-paper px-4 py-2 text-sm font-bold"
                            @click="modal = 'perbaikan'"
                        >Minta Perbaikan</button>
                        <button
                            type="button"
                            class="brutal-sm brutal-hover bg-accent-100 px-4 py-2 text-sm font-bold"
                            @click="modal = 'tolak'"
                        >Tolak</button>
                    </div>

                    <p v-else class="mt-6 border-t-2 border-ink pt-4 text-sm text-muted">
                        Pengajuan ini sudah diproses dan tidak dapat diubah lagi.
                    </p>
                </div>
            </div>
        </div>

        <!-- Konfirmasi setujui -->
        <div v-if="konfirmasiSetujui && terpilih" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="konfirmasiSetujui = false">
            <div class="brutal w-full max-w-md bg-paper p-5">
                <h2 class="font-display text-lg">Setujui pengajuan?</h2>
                <p class="mt-2 text-sm">
                    <strong>{{ terpilih.nama }}</strong> akan menjadi
                    {{ terpilih.jalur === 'kader' ? 'Kader Aktif' : 'Alumni' }}.
                </p>
                <ul class="mt-3 list-inside list-disc text-sm text-muted">
                    <li>Nomor anggota diterbitkan otomatis</li>
                    <li v-if="terpilih.jalur === 'kader'">Kartu kader digital dibuat</li>
                    <li>Email pemberitahuan dikirim ke pemohon</li>
                    <li>Perubahan ini tercatat pada riwayat status</li>
                </ul>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="konfirmasiSetujui = false">Batal</button>
                    <button type="button" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" @click="setujui(terpilih)">
                        Ya, setujui
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal catatan (tolak / minta perbaikan) -->
        <div v-if="modal && terpilih" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="modal = null">
            <form class="brutal w-full max-w-lg bg-paper p-5" @submit.prevent="kirimCatatan">
                <h2 class="font-display text-lg">
                    {{ modal === 'tolak' ? 'Tolak Pengajuan' : 'Minta Perbaikan' }}
                </h2>
                <p class="mt-1 text-sm text-muted">
                    {{ modal === 'tolak'
                        ? 'Alasan wajib diisi — pemohon akan menerimanya lewat email.'
                        : 'Sebutkan bagian mana yang perlu dilengkapi pemohon.' }}
                </p>

                <label for="catatan" class="mt-4 block text-sm font-bold">Catatan untuk pemohon *</label>
                <textarea
                    id="catatan"
                    v-model="formCatatan.catatan_pengurus"
                    rows="5"
                    required
                    minlength="10"
                    class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                />
                <p class="mt-1 text-xs text-muted">Minimal 10 karakter.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modal = null">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formCatatan.processing">
                        {{ modal === 'tolak' ? 'Tolak & kirim email' : 'Kirim permintaan' }}
                    </button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
