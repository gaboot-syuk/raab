<script setup lang="ts">
/*
 * Kegiatan & presensi dari sisi kader.
 *
 * Dua bagian yang sengaja dipisah: KESEDIAAN HADIR untuk kegiatan yang belum
 * berlangsung, dan RIWAYAT KEHADIRAN untuk yang sudah lewat. Menggabungkannya
 * membuat kader sulit tahu mana yang masih bisa ia ubah.
 *
 * Kehadiran TIDAK diisi dari sini — hanya panitia di pintu, atau kader sendiri
 * dengan memindai kode QR panitia.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AkanDatang {
    id: number;
    kode: string;
    judul: string;
    label_jenis: string;
    mulai: string | null;
    lokasi: string | null;
    poin: number;
    wajib: boolean;
    mode: string;
    sedang_terbuka: boolean;
    rsvp: string | null;
    label_rsvp: string | null;
    catatan_rsvp: string | null;
    sudah_presensi: boolean;
    label_presensi: string | null;
}

interface Riwayat {
    id: number;
    kode: string;
    judul: string;
    mulai: string | null;
    poin: number;
    status: string | null;
    label_status: string | null;
    metode: string | null;
    dihitung_hadir: boolean;
}

const props = defineProps<{
    anggota: boolean;
    akanDatang: AkanDatang[];
    riwayat: Riwayat[];
    pilihanRsvp?: Record<string, string>;
    rekap: {
        hadir: number;
        terlambat: number;
        izin: number;
        sakit: number;
        alpa: number;
        total_hadir: number;
        total_kegiatan: number;
        persen: number;
        poin: number;
    } | null;
    catatan?: string;
}>();

const catatanUntuk = ref<number | null>(null);
const catatan = ref('');

function rsvp(kegiatan: AkanDatang, status: string): void {
    const isi = catatanUntuk.value === kegiatan.id ? catatan.value : null;

    router.post(`/kegiatan/${kegiatan.id}/rsvp`, { status, catatan: isi }, {
        preserveScroll: true,
        onSuccess: () => {
            catatanUntuk.value = null;
            catatan.value = '';
        },
    });
}
</script>

<template>
    <Head title="Kegiatan" />

    <AnggotaLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div>
                <h1 class="font-display text-2xl">Kegiatan &amp; Presensi</h1>
                <p class="text-sm text-muted">Kesediaan hadir, riwayat kehadiran, dan poin kontribusimu dari kegiatan.</p>
            </div>

            <p v-if="!props.anggota" class="brutal bg-paper p-5 text-sm text-muted">
                Akunmu belum terhubung ke data anggota, jadi kegiatan belum bisa ditampilkan.
                Hubungi Sekretaris rayon bila kamu merasa sudah terverifikasi.
            </p>

            <template v-else>
                <!-- ===== Rekap saya ===== -->
                <section v-if="props.rekap" class="brutal bg-brand-dark p-5 text-on-brand">
                    <p class="text-xs font-bold uppercase tracking-wide text-on-brand/70">Kehadiranmu</p>
                    <p class="mt-2 font-display text-3xl">{{ props.rekap.persen }}%</p>
                    <p class="mt-1 text-sm text-on-brand/85">
                        Hadir {{ props.rekap.total_hadir }} dari {{ props.rekap.total_kegiatan }} kegiatan yang sudah dibuka.
                    </p>
                    <p class="mt-3 flex flex-wrap gap-3 text-xs text-on-brand/85">
                        <span>Hadir {{ props.rekap.hadir }}</span>
                        <span>Terlambat {{ props.rekap.terlambat }}</span>
                        <span>Izin {{ props.rekap.izin }}</span>
                        <span>Sakit {{ props.rekap.sakit }}</span>
                        <span>Tanpa ket. {{ props.rekap.alpa }}</span>
                    </p>
                    <p class="mt-3 font-display text-lg">{{ props.rekap.poin }} poin</p>
                    <p class="text-[11px] text-on-brand/70">
                        Poin dihitung hanya dari kegiatan yang kamu hadiri.
                    </p>
                </section>

                <p v-if="props.catatan" class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

                <!-- ===== Kegiatan yang sedang terbuka ===== -->
                <section>
                    <h2 class="font-display text-lg">Sedang Berlangsung</h2>

                    <p v-if="!props.akanDatang.length" class="mt-2 brutal bg-paper p-5 text-sm text-muted">
                        Belum ada kegiatan yang presensinya dibuka. Kegiatan akan muncul di sini begitu panitia membukanya.
                    </p>

                    <ul v-else class="mt-3 space-y-4">
                        <li v-for="k in props.akanDatang" :key="k.id" class="brutal bg-paper p-5">
                            <p class="font-mono text-xs text-muted">{{ k.kode }} · {{ k.label_jenis }}</p>
                            <h3 class="font-display text-lg leading-tight">{{ k.judul }}</h3>
                            <p class="mt-1 text-sm text-muted">
                                {{ k.mulai }} WIB
                                <span v-if="k.lokasi"> · {{ k.lokasi }}</span>
                            </p>

                            <p class="mt-2 flex flex-wrap gap-2 text-[10px] font-bold uppercase">
                                <span class="border-2 border-ink bg-accent-100 px-2 py-0.5">Presensi Terbuka</span>
                                <span v-if="k.wajib" class="border-2 border-ink bg-accent-400 px-2 py-0.5">Wajib</span>
                                <span v-if="k.poin > 0" class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ k.poin }} poin</span>
                                <span class="border-2 border-ink bg-paper-alt px-2 py-0.5">{{ k.mode }}</span>
                            </p>

                            <p v-if="k.sudah_presensi" class="mt-3 border-2 border-ink bg-accent-100 p-3 text-xs font-bold">
                                Kehadiranmu sudah tercatat: {{ k.label_presensi }}.
                            </p>
                            <p v-else class="mt-3 text-xs text-muted">
                                Kehadiranmu belum tercatat. Pindai kode QR yang ditampilkan panitia, atau minta panitia mencatatmu di pintu.
                            </p>

                            <div class="mt-3">
                                <p class="text-xs font-bold uppercase text-muted">Kesediaan hadir</p>
                                <p class="text-[11px] text-muted">
                                    Kesediaan hanya perkiraan untuk panitia — kehadiran tetap dicatat di pintu.
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="(label, kunci) in props.pilihanRsvp"
                                        :key="kunci"
                                        type="button"
                                        class="brutal-sm px-3 py-1.5 text-xs font-bold"
                                        :class="k.rsvp === kunci ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                                        @click="rsvp(k, kunci)"
                                    >{{ label }}</button>

                                    <span class="text-[11px] text-muted">
                                        {{ k.label_rsvp ? `Jawabanmu: ${k.label_rsvp}` : 'Belum menjawab' }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- ===== Riwayat ===== -->
                <section>
                    <h2 class="font-display text-lg">Riwayat Kehadiran</h2>

                    <p v-if="!props.riwayat.length" class="mt-2 text-sm text-muted">Belum ada riwayat kegiatan.</p>

                    <table v-else class="mt-3 w-full text-sm">
                        <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                            <tr>
                                <th class="py-2">Kegiatan</th>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2">Kehadiran</th>
                                <th class="py-2 text-right">Poin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in props.riwayat" :key="r.id" class="border-b-2 border-ink/10">
                                <td class="py-2">
                                    <span class="font-bold">{{ r.judul }}</span>
                                    <span class="block font-mono text-[10px] text-muted">{{ r.kode }}</span>
                                </td>
                                <td class="py-2 text-xs">{{ r.mulai }}</td>
                                <td class="py-2">
                                    <span
                                        v-if="r.label_status"
                                        class="border-2 border-ink px-2 py-0.5 text-[10px] font-bold uppercase"
                                        :class="r.dihitung_hadir ? 'bg-accent-100' : 'bg-paper-alt'"
                                    >{{ r.label_status }}</span>
                                    <span v-else class="text-xs text-muted">Tidak diikuti</span>

                                    <span v-if="r.metode" class="block text-[10px] text-muted">{{ r.metode }}</span>
                                </td>
                                <td class="py-2 text-right text-xs">
                                    {{ r.dihitung_hadir && r.poin > 0 ? `+${r.poin}` : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
        </div>
    </AnggotaLayout>
</template>
