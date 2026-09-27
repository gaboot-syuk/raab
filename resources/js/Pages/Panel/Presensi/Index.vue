<script setup lang="ts">
/*
 * Rekap & pencatatan kehadiran satu kegiatan.
 *
 * DAFTARNYA MEMUAT SELURUH KADER AKTIF, bukan hanya yang sudah hadir. Kalau
 * hanya yang sudah hadir yang tampil, panitia tidak punya cara menandai orang
 * yang baru datang di pintu.
 *
 * Menandai seseorang atau beberapa orang HADIR bisa borongan; IZIN, SAKIT, dan
 * TANPA KETERANGAN hanya satu per satu — menuduh orang membolos lewat satu
 * tombol adalah tindakan yang tidak boleh semudah itu.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Peserta {
    id: number;
    nama: string;
    nomor_anggota: string | null;
    rsvp: string | null;
    rsvp_status: string | null;
    status: string | null;
    label_status: string | null;
    metode: string | null;
    dicatat_pada: string | null;
}

const props = defineProps<{
    kegiatan: {
        id: number;
        kode: string;
        judul: string;
        label_jenis: string;
        label_status: string;
        status: string;
        label_mode: string;
        mulai: string | null;
        lokasi: string | null;
        unit: string | null;
        poin: number;
        qr_aktif: boolean;
        boleh_dicatat: boolean;
    } | null;
    daftarKegiatan: { id: number; label: string; status: string }[];
    peserta: Peserta[];
    rekap: { hadir: number; terlambat: number; izin: number; sakit: number; alpa: number; belum: number; total_hadir: number; total_anggota: number; rsvp_hadir: number } | null;
    pilihanStatus: Record<string, string>;
    catatan: string;
}>();

const dipilih = ref<number[]>([]);
const sisaUntuk = ref(false);
const formSisa = useForm({ alasan: '', anggota: [] as number[] });

const belumAdaCatatan = computed(() => props.peserta.filter((p) => p.status === null).map((p) => p.id));

function gantiKegiatan(id: number): void {
    dipilih.value = [];
    router.get('/panel/presensi', { kegiatan: id }, { preserveScroll: true });
}

function ubahStatus(p: Peserta, status: string): void {
    if (!status) {
        return;
    }

    router.post(`/panel/presensi/${props.kegiatan!.id}/anggota/${p.id}`, { status }, { preserveScroll: true });
}

function tandaiHadir(daftar: number[], status = 'hadir'): void {
    if (!daftar.length) {
        return;
    }

    router.post(`/panel/presensi/${props.kegiatan!.id}/massal`, { anggota: daftar, status }, {
        preserveScroll: true,
        onSuccess: () => {
            dipilih.value = [];
        },
    });
}

function kirimSisa(): void {
    formSisa.anggota = belumAdaCatatan.value;

    formSisa.post(`/panel/presensi/${props.kegiatan!.id}/sisa`, {
        preserveScroll: true,
        onSuccess: () => {
            sisaUntuk.value = false;
            formSisa.reset();
        },
    });
}
</script>

<template>
    <Head title="Presensi" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl">Presensi</h1>
                <p class="text-sm text-muted">Rekap dan pencatatan kehadiran per kegiatan.</p>
            </div>

            <label class="block max-w-xl">
                <span class="text-xs font-bold uppercase text-muted">Pilih kegiatan</span>
                <select
                    class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                    :value="props.kegiatan?.id ?? ''"
                    @change="gantiKegiatan(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option value="" disabled>— Pilih kegiatan —</option>
                    <option v-for="k in props.daftarKegiatan" :key="k.id" :value="k.id">{{ k.label }}</option>
                </select>
            </label>

            <p v-if="!props.kegiatan" class="brutal bg-paper p-5 text-sm text-muted">{{ props.catatan }}</p>

            <template v-else>
                <!-- ===== Kepala kegiatan ===== -->
                <section class="brutal bg-paper p-5">
                    <p class="font-mono text-xs text-muted">{{ props.kegiatan.kode }} · {{ props.kegiatan.label_jenis }}</p>
                    <h2 class="font-display text-xl leading-tight">{{ props.kegiatan.judul }}</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ props.kegiatan.mulai }} WIB
                        <span v-if="props.kegiatan.lokasi"> · {{ props.kegiatan.lokasi }}</span>
                        <span v-if="props.kegiatan.unit"> · {{ props.kegiatan.unit }}</span>
                    </p>

                    <p class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                        <span class="border-2 border-ink px-2 py-0.5" :class="props.kegiatan.status === 'terbuka' ? 'bg-accent-100' : 'bg-paper-alt'">
                            {{ props.kegiatan.label_status }}
                        </span>
                        <span class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ props.kegiatan.label_mode }}</span>
                        <span v-if="props.kegiatan.poin > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ props.kegiatan.poin }} poin</span>
                    </p>

                    <p v-if="!props.kegiatan.boleh_dicatat" class="mt-3 border-2 border-ink bg-accent-400 p-3 text-xs font-bold">
                        Presensi belum dibuka atau sudah ditutup — kehadiran tidak dapat dicatat sekarang.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a :href="`/panel/kegiatan/${props.kegiatan.id}/qr`" target="_blank" rel="noopener" class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold">
                            Tampilkan QR
                        </a>
                        <a :href="`/panel/presensi/${props.kegiatan.id}/ekspor`" class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold">
                            Ekspor CSV
                        </a>
                    </div>
                </section>

                <!-- ===== Rekap ===== -->
                <section v-if="props.rekap" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Hadir</p>
                        <p class="font-display text-2xl">{{ props.rekap.hadir }}</p>
                    </div>
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Terlambat</p>
                        <p class="font-display text-2xl">{{ props.rekap.terlambat }}</p>
                    </div>
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Izin</p>
                        <p class="font-display text-2xl">{{ props.rekap.izin }}</p>
                    </div>
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Sakit</p>
                        <p class="font-display text-2xl">{{ props.rekap.sakit }}</p>
                    </div>
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Tanpa Ket.</p>
                        <p class="font-display text-2xl">{{ props.rekap.alpa }}</p>
                    </div>
                    <div class="brutal bg-paper p-4">
                        <p class="text-[10px] font-bold uppercase text-muted">Belum Ada</p>
                        <p class="font-display text-2xl">{{ props.rekap.belum }}</p>
                    </div>
                </section>

                <p v-if="props.rekap" class="text-xs text-muted">
                    {{ props.rekap.total_hadir }} dari {{ props.rekap.total_anggota }} kader aktif tercatat hadir.
                    {{ props.rekap.rsvp_hadir }} menyatakan akan hadir sebelum kegiatan.
                </p>

                <!-- ===== Aksi borongan ===== -->
                <section v-if="props.kegiatan.boleh_dicatat" class="brutal bg-paper-alt p-4">
                    <p class="text-xs font-bold uppercase text-muted">Tandai serentak</p>
                    <p class="mt-1 text-xs text-muted">
                        Izin, sakit, dan tanpa keterangan dicatat satu per satu di bawah — menandai massal hanya untuk kehadiran.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800" @click="tandaiHadir(dipilih)">
                            Tandai Hadir ({{ dipilih.length }})
                        </button>
                        <button type="button" class="brutal-sm brutal-hover bg-paper px-3 py-1.5 text-xs font-bold" @click="tandaiHadir(dipilih, 'terlambat')">
                            Tandai Terlambat ({{ dipilih.length }})
                        </button>
                        <button
                            type="button"
                            class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold"
                            @click="dipilih = props.peserta.filter((p) => p.rsvp_status === 'hadir').map((p) => p.id)"
                        >
                            Pilih yang menyatakan hadir
                        </button>
                        <button type="button" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold" @click="dipilih = belumAdaCatatan">
                            Pilih yang belum tercatat
                        </button>
                    </div>
                </section>

                <!-- ===== Daftar peserta ===== -->
                <table class="w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="w-10 py-2"></th>
                            <th class="py-2">Kader</th>
                            <th class="py-2">Kesediaan</th>
                            <th class="py-2">Kehadiran</th>
                            <th class="py-2">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in props.peserta" :key="p.id" class="border-b-2 border-ink/10">
                            <td class="py-2">
                                <input v-model="dipilih" type="checkbox" :value="p.id" class="h-4 w-4 border-2 border-ink">
                            </td>
                            <td class="py-2">
                                <span class="font-bold">{{ p.nama }}</span>
                                <span v-if="p.nomor_anggota" class="block font-mono text-[10px] text-muted">{{ p.nomor_anggota }}</span>
                            </td>
                            <td class="py-2 text-xs text-muted">{{ p.rsvp ?? '—' }}</td>
                            <td class="py-2">
                                <span
                                    v-if="p.label_status"
                                    class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase"
                                    :class="p.status === 'hadir' || p.status === 'terlambat' ? 'bg-accent-100' : 'bg-paper-alt'"
                                >{{ p.label_status }}</span>
                                <span v-else class="text-xs text-muted">Belum ada catatan</span>

                                <span v-if="p.metode" class="block text-[10px] text-muted">{{ p.metode }}</span>
                            </td>
                            <td class="py-2 text-xs">
                                <span v-if="props.kegiatan.boleh_dicatat">
                                    <select
                                        class="brutal-sm bg-paper-alt px-2 py-1 text-xs"
                                        :value="p.status ?? ''"
                                        @change="ubahStatus(p, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">— Belum ada —</option>
                                        <option v-for="(label, kunci) in props.pilihanStatus" :key="kunci" :value="kunci">{{ label }}</option>
                                    </select>
                                </span>
                                <span v-else>{{ p.dicatat_pada ?? '—' }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- ===== Tandai sisa ===== -->
                <section v-if="props.kegiatan.boleh_dicatat" class="brutal bg-paper p-4">
                    <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="sisaUntuk = !sisaUntuk">
                        {{ sisaUntuk ? 'Tutup' : `Tandai ${belumAdaCatatan.length} yang belum tercatat sebagai tanpa keterangan` }}
                    </button>

                    <form v-if="sisaUntuk" class="mt-3 border-t-2 border-ink/10 pt-3" @submit.prevent="kirimSisa">
                        <p class="text-xs text-muted">
                            Tindakan ini menulis catatan "Tanpa Keterangan" untuk
                            <strong>{{ belumAdaCatatan.length }}</strong> kader yang belum punya catatan, dan tercatat di log aktivitas.
                            Lakukan hanya setelah daftar di pintu benar-benar lengkap.
                        </p>

                        <label class="mt-3 block">
                            <span class="text-xs font-bold uppercase text-muted">Alasan *</span>
                            <input v-model="formSisa.alasan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formSisa.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formSisa.errors.alasan }}</span>
                        </label>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" :disabled="formSisa.processing" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">
                                Tandai Sekarang
                            </button>
                            <button type="button" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold" @click="sisaUntuk = false">Batal</button>
                        </div>
                    </form>
                </section>
            </template>
        </div>
    </PanelLayout>
</template>
