<script setup lang="ts">
/*
 * Pengelolaan keanggotaan (Sekretaris).
 * Data sensitif (NIM, telepon) hanya dikirim server bila pengguna memegang
 * izin members.view-sensitive — jadi kolomnya bisa kosong untuk peran lain.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Anggota {
    id: number;
    nomor_anggota: string | null;
    nama: string;
    jalur: string;
    label_jalur: string;
    status: string;
    label_status: string;
    angkatan: number | null;
    fakultas: string | null;
    program_studi: string | null;
    unit: string | null;
    nim: string | null;
    telepon: string | null;
    punya_kartu: boolean;
    kartu_aktif: boolean;
    kelengkapan: number;
}

const props = defineProps<{
    daftar: {
        data: Anggota[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    saring: { status: string; jalur: string; angkatan: string; unit: string; cari: string };
    bolehSensitif: boolean;
    jumlah: { semua: number; aktif: number; alumni: number; menunggu: number; nonaktif: number };
    pilihanStatus: Record<string, string>;
    pilihanJalur: Record<string, string>;
    daftarAngkatan: number[];
    daftarUnit: { id: number; nama: string; jenis: string }[];
}>();

const cariLokal = ref(props.saring.cari);
const terpilih = ref<number[]>([]);

const tab = computed(() => [
    { nilai: 'semua', label: 'Semua', jumlah: props.jumlah.semua },
    { nilai: 'aktif', label: 'Kader Aktif', jumlah: props.jumlah.aktif },
    { nilai: 'alumni', label: 'Alumni', jumlah: props.jumlah.alumni },
    { nilai: 'menunggu', label: 'Menunggu', jumlah: props.jumlah.menunggu },
    { nilai: 'nonaktif', label: 'Nonaktif', jumlah: props.jumlah.nonaktif },
]);

function saringUlang(tambahan: Record<string, string>) {
    router.get(
        '/panel/keanggotaan',
        { ...props.saring, cari: cariLokal.value, ...tambahan },
        { preserveState: true, preserveScroll: true },
    );
}

function semuaTerpilih(nilai: boolean) {
    terpilih.value = nilai && hanyaKaderTerpilih.value
        ? props.daftar.data.filter((a) => a.status === 'aktif').map((a) => a.id)
        : [];
}

const hanyaKaderTerpilih = computed(() =>
    props.daftar.data.some((a) => a.status === 'aktif'),
);

const formMassal = useForm({ anggota: [] as number[], alasan: '' });
const modalMassal = ref(false);

function jadikanAlumni() {
    formMassal.anggota = terpilih.value;
    formMassal.post('/panel/keanggotaan/alumni-massal', {
        preserveScroll: true,
        onSuccess: () => {
            modalMassal.value = false;
            terpilih.value = [];
            formMassal.reset();
        },
    });
}

const formStatus = useForm({ status: '', alasan: '' });
const modalStatus = ref<Anggota | null>(null);

function bukaStatus(anggota: Anggota) {
    modalStatus.value = anggota;
    formStatus.status = anggota.status;
    formStatus.alasan = '';
    formStatus.clearErrors();
}

function simpanStatus() {
    if (!modalStatus.value) {
        return;
    }

    formStatus.patch(`/panel/keanggotaan/${modalStatus.value.id}/status`, {
        preserveScroll: true,
        onSuccess: () => (modalStatus.value = null),
    });
}
</script>

<template>
    <PanelLayout>
        <Head title="Keanggotaan" />

        <div class="mx-auto max-w-7xl space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Keanggotaan</h1>
                <p class="mt-1 text-sm text-muted">
                    Daftar kader aktif, alumni, dan pendaftar. Perubahan status selalu tercatat pada riwayat.
                </p>
            </div>

            <PesanHasil />

            <!-- Saringan status -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="t in tab"
                    :key="t.nilai"
                    type="button"
                    class="border-2 border-ink px-3 py-1.5 text-sm font-bold"
                    :class="saring.status === t.nilai ? 'bg-accent-400 text-primary-800' : 'bg-paper hover:bg-accent-100'"
                    @click="saringUlang({ status: t.nilai })"
                >
                    {{ t.label }}
                    <span class="ml-1 border-2 border-ink bg-paper px-1 text-[11px]">{{ t.jumlah }}</span>
                </button>
            </div>

            <!-- Saringan lain -->
            <div class="brutal grid gap-3 bg-paper p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="jalur" class="block text-xs font-bold uppercase text-muted">Jalur</label>
                    <select id="jalur" :value="saring.jalur" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saringUlang({ jalur: ($event.target as HTMLSelectElement).value })">
                        <option value="semua">Semua</option>
                        <option v-for="(label, nilai) in pilihanJalur" :key="nilai" :value="nilai">{{ label }}</option>
                    </select>
                </div>

                <div>
                    <label for="angkatan" class="block text-xs font-bold uppercase text-muted">Angkatan</label>
                    <select id="angkatan" :value="saring.angkatan" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saringUlang({ angkatan: ($event.target as HTMLSelectElement).value })">
                        <option value="">Semua</option>
                        <option v-for="tahun in daftarAngkatan" :key="tahun" :value="String(tahun)">{{ tahun }}</option>
                    </select>
                </div>

                <div>
                    <label for="unit" class="block text-xs font-bold uppercase text-muted">Biro / LSO</label>
                    <select id="unit" :value="saring.unit" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" @change="saringUlang({ unit: ($event.target as HTMLSelectElement).value })">
                        <option value="">Semua</option>
                        <option v-for="u in daftarUnit" :key="u.id" :value="String(u.id)">{{ u.nama }}</option>
                    </select>
                </div>

                <form class="flex items-end gap-2" @submit.prevent="saringUlang({})">
                    <input v-model="cariLokal" type="search" placeholder="Nama / nomor anggota / NIM" class="brutal-sm w-full bg-paper-alt px-3 py-2 text-sm">
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-3 py-2 text-sm font-bold text-paper">Cari</button>
                </form>
            </div>

            <!-- Aksi massal -->
            <div v-if="terpilih.length" class="brutal-sm flex flex-wrap items-center gap-3 border-ink bg-accent-100 px-4 py-3">
                <span class="text-sm font-bold">{{ terpilih.length }} anggota dipilih</span>
                <button type="button" class="brutal-sm brutal-hover bg-primary-600 px-3 py-1.5 text-sm font-bold text-paper" @click="modalMassal = true">
                    Jadikan Alumni
                </button>
                <button type="button" class="brutal-sm bg-paper px-3 py-1.5 text-sm font-bold" @click="terpilih = []">Batal pilih</button>
            </div>

            <p v-if="!bolehSensitif" class="brutal-sm border-ink bg-paper-alt px-4 py-3 text-xs text-muted">
                NIM dan nomor telepon disembunyikan karena peranmu tidak memegang izin
                <em>Melihat data sensitif</em>.
            </p>

            <!-- Tabel -->
            <div class="brutal overflow-x-auto bg-paper">
                <table class="w-full min-w-[60rem] text-sm">
                    <thead class="border-b-2 border-ink bg-paper-alt text-left">
                        <tr>
                            <th class="px-3 py-3">
                                <input type="checkbox" class="h-4 w-4 border-2 border-ink" :checked="terpilih.length > 0" @change="semuaTerpilih(($event.target as HTMLInputElement).checked)">
                            </th>
                            <th class="px-3 py-3 font-display">Nama</th>
                            <th class="px-3 py-3 font-display">Nomor</th>
                            <th class="px-3 py-3 font-display">Status</th>
                            <th class="px-3 py-3 font-display">Angkatan</th>
                            <th class="px-3 py-3 font-display">Unit</th>
                            <th class="px-3 py-3 font-display">Kartu</th>
                            <th class="px-3 py-3 font-display">Profil</th>
                            <th class="px-3 py-3 text-right font-display">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in daftar.data" :key="a.id" class="border-b-2 border-ink/15">
                            <td class="px-3 py-3">
                                <input v-model="terpilih" type="checkbox" :value="a.id" class="h-4 w-4 border-2 border-ink">
                            </td>
                            <td class="px-3 py-3">
                                <p class="font-bold">{{ a.nama }}</p>
                                <p class="text-xs text-muted">
                                    {{ a.program_studi || a.fakultas || '—' }}
                                    <span v-if="a.nim"> · {{ a.nim }}</span>
                                    <span v-if="a.telepon"> · {{ a.telepon }}</span>
                                </p>
                            </td>
                            <td class="px-3 py-3 font-mono text-xs">{{ a.nomor_anggota || '—' }}</td>
                            <td class="px-3 py-3">
                                <span
                                    class="border-2 border-ink px-1.5 py-0.5 text-[11px] font-bold uppercase"
                                    :class="a.status === 'aktif' ? 'bg-success/25' : a.status === 'alumni' ? 'bg-accent-100' : 'bg-paper-alt text-muted'"
                                >{{ a.label_status }}</span>
                            </td>
                            <td class="px-3 py-3">{{ a.angkatan || '—' }}</td>
                            <td class="px-3 py-3">{{ a.unit || '—' }}</td>
                            <td class="px-3 py-3 text-xs">
                                <span v-if="a.kartu_aktif" class="font-bold">Aktif</span>
                                <span v-else-if="a.punya_kartu" class="text-muted">Dicabut</span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="h-2 w-16 border-2 border-ink bg-paper-alt">
                                    <div class="h-full bg-accent-400" :style="{ width: a.kelengkapan + '%' }"></div>
                                </div>
                                <span class="text-[11px] font-bold">{{ a.kelengkapan }}%</span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-1">
                                    <Link :href="`/panel/keanggotaan/${a.id}`" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold">Detail</Link>
                                    <button type="button" class="brutal-sm brutal-hover bg-accent-100 px-2 py-1 text-xs font-bold" @click="bukaStatus(a)">Ubah Status</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!daftar.data.length">
                            <td colspan="9" class="px-4 py-10 text-center text-muted">Tidak ada anggota yang cocok dengan saringan ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Penomoran :tautan="daftar.links" />
        </div>

        <!-- Modal ubah status -->
        <div v-if="modalStatus" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="modalStatus = null">
            <form class="brutal w-full max-w-md bg-paper p-5" @submit.prevent="simpanStatus">
                <h2 class="font-display text-lg">Ubah Status Keanggotaan</h2>
                <p class="mt-1 text-sm text-muted">{{ modalStatus.nama }} — saat ini {{ modalStatus.label_status }}</p>

                <label for="status-baru" class="mt-4 block text-sm font-bold">Status baru</label>
                <select id="status-baru" v-model="formStatus.status" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    <option v-for="(label, nilai) in pilihanStatus" :key="nilai" :value="nilai">{{ label }}</option>
                </select>

                <label for="alasan" class="mt-3 block text-sm font-bold">Alasan / keterangan</label>
                <textarea id="alasan" v-model="formStatus.alasan" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" placeholder="Mis. telah lulus pada September 2026." />

                <ul class="mt-3 list-inside list-disc text-xs text-muted">
                    <li>Memilih <strong>Alumni</strong> akan mencabut kartu kader & membuat profil alumni.</li>
                    <li>Pemilik akun menerima email pemberitahuan.</li>
                </ul>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalStatus = null">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formStatus.processing">Simpan</button>
                </div>
            </form>
        </div>

        <!-- Modal alumni massal -->
        <div v-if="modalMassal" class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4" @click.self="modalMassal = false">
            <form class="brutal w-full max-w-md bg-paper p-5" @submit.prevent="jadikanAlumni">
                <h2 class="font-display text-lg">Jadikan Alumni</h2>
                <p class="mt-2 text-sm">
                    {{ terpilih.length }} anggota akan dipindahkan ke direktori alumni.
                    Hanya yang berstatus <strong>Kader Aktif</strong> yang diproses.
                </p>
                <label for="alasan-massal" class="mt-4 block text-sm font-bold">Alasan (dipakai pada riwayat & email)</label>
                <input id="alasan-massal" v-model="formMassal.alasan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" placeholder="Mis. wisuda periode September 2026">
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalMassal = false">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formMassal.processing">Proses</button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
