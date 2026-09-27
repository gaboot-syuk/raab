<script setup lang="ts">
/*
 * Poin kontribusi: buku besar, penyesuaian manual, papan peringkat.
 *
 * BUKU BESAR, BUKAN SATU ANGKA. Halaman ini menampilkan daftar peristiwanya.
 * Pertanyaan kader yang paling sering muncul adalah "kok poin saya segitu?" —
 * dan itu hanya bisa dijawab kalau asalnya kelihatan.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Catatan {
    id: number;
    anggota: string;
    sumber: string;
    label_sumber: string;
    poin: number;
    keterangan: string;
    periode: string;
    terjadi_pada: string | null;
    kegiatan: string | null;
    pemberi: string | null;
    dibatalkan: boolean;
    alasan_pembatalan: string | null;
    pembatal: string | null;
}

interface Peringkat {
    peringkat: number;
    anggota_id: number;
    nama: string;
    unit: string | null;
    poin: number;
    jumlah_peristiwa: number;
    total_hadir: number;
}

const props = defineProps<{
    catatan: Catatan[];
    papanPeringkat: Peringkat[];
    pilihanSumber: Record<string, string>;
    pilihanPeriode: string[];
    pilihanAnggota: { id: number; nama: string; total: number }[];
    kegiatanBelumDisinkron: { id: number; label: string }[];
    batasPenyesuaian: number;
    saringan: { periode: string; sumber: string; anggota: number | null };
    catatanHalaman: string;
}>();

const periodeSaring = ref(props.saringan.periode);
const sumberSaring = ref(props.saringan.sumber);
const batalUntuk = ref<number | null>(null);
const formBatal = useForm({ alasan: '' });

const form = useForm({
    member_id: null as number | null,
    poin: 0,
    alasan: '',
    periode: props.saringan.periode,
});

function saring(): void {
    router.get('/panel/kontribusi', {
        periode: periodeSaring.value,
        sumber: sumberSaring.value,
    }, { preserveScroll: true });
}

function simpan(): void {
    form.post('/panel/kontribusi/penyesuaian', {
        preserveScroll: true,
        onSuccess: () => form.reset('poin', 'alasan'),
    });
}

function sinkronkan(kegiatanId: number, label: string): void {
    if (!confirm(`Beri poin untuk semua peserta yang hadir pada ${label}? Yang sudah punya poin akan dilewati.`)) {
        return;
    }

    router.post(`/panel/kontribusi/kegiatan/${kegiatanId}/sinkronkan`, {}, { preserveScroll: true });
}

function kirimBatal(c: Catatan): void {
    formBatal.post(`/panel/kontribusi/${c.id}/batalkan`, {
        preserveScroll: true,
        onSuccess: () => {
            batalUntuk.value = null;
            formBatal.reset();
        },
    });
}
</script>

<template>
    <Head title="Poin Kontribusi" />

    <PanelLayout>
        <div class="space-y-6">
            <PesanHasil />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">Poin Kontribusi</h1>
                    <p class="text-sm text-muted">Buku besar poin, penyesuaian, dan papan peringkat kader.</p>
                </div>

                <a
                    :href="`/panel/kontribusi/ekspor${periodeSaring ? `?periode=${periodeSaring}` : ''}`"
                    class="brutal-sm brutal-hover bg-paper-alt px-4 py-2 text-sm font-bold"
                >Ekspor CSV</a>
            </div>

            <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatanHalaman }}</p>

            <!-- ===== Saringan ===== -->
            <div class="flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Periode</span>
                    <select v-model="periodeSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua periode</option>
                        <option v-for="p in props.pilihanPeriode" :key="p" :value="p">{{ p }}</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-bold uppercase text-muted">Sumber</span>
                    <select v-model="sumberSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                        <option value="">Semua sumber</option>
                        <option v-for="(label, kunci) in props.pilihanSumber" :key="kunci" :value="kunci">{{ label }}</option>
                    </select>
                </label>
            </div>

            <!-- ===== Papan peringkat ===== -->
            <section class="brutal bg-brand-dark p-5 text-on-brand">
                <p class="text-xs font-bold uppercase tracking-wide text-on-brand/70">Papan Peringkat Kader Teraktif</p>
                <p class="text-[11px] text-on-brand/70">
                    {{ periodeSaring ? `Periode ${periodeSaring}` : 'Seluruh periode' }} · hanya kader aktif
                </p>

                <p v-if="!props.papanPeringkat.length" class="mt-3 text-sm text-on-brand/85">
                    Belum ada poin tercatat. Poin muncul begitu kehadiran kegiatan dicatat.
                </p>

                <ol v-else class="mt-4 space-y-2">
                    <li
                        v-for="p in props.papanPeringkat"
                        :key="p.anggota_id"
                        class="flex flex-wrap items-baseline justify-between gap-2 border-2 border-on-brand/25 px-3 py-2"
                    >
                        <span class="min-w-0">
                            <span class="font-mono text-xs text-on-brand/70">#{{ p.peringkat }}</span>
                            <span class="ml-2 font-bold">{{ p.nama }}</span>
                            <span v-if="p.unit" class="ml-2 text-[11px] text-on-brand/70">{{ p.unit }}</span>
                        </span>

                        <span class="text-right">
                            <span class="font-display text-lg">{{ p.poin }}</span>
                            <span class="ml-2 text-[11px] text-on-brand/70">
                                {{ p.jumlah_peristiwa }} peristiwa · {{ p.total_hadir }} hadir
                            </span>
                        </span>
                    </li>
                </ol>

                <p class="mt-3 text-[11px] text-on-brand/70">
                    Seri diputus oleh banyaknya peristiwa — 20 poin dari 4 kegiatan tidak sama dengan 20 poin dari 2 kegiatan.
                </p>
            </section>

            <!-- ===== Sinkronisasi poin kegiatan ===== -->
            <section v-if="props.kegiatanBelumDisinkron.length" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Beri Poin Kegiatan</h2>
                <p class="mt-1 text-xs text-muted">
                    Poin sudah otomatis diberikan saat kehadiran dicatat. Tombol ini untuk kegiatan yang
                    kehadirannya tercatat sebelum fitur poin ada, atau bila ada yang terlewat.
                    Menjalankannya berulang aman — yang sudah berpoin dilewati.
                </p>

                <ul class="mt-3 flex flex-wrap gap-2">
                    <li v-for="k in props.kegiatanBelumDisinkron" :key="k.id">
                        <button
                            type="button"
                            class="brutal-sm brutal-hover bg-paper-alt px-3 py-1.5 text-xs font-bold"
                            @click="sinkronkan(k.id, k.label)"
                        >{{ k.label }}</button>
                    </li>
                </ul>
            </section>

            <!-- ===== Penyesuaian manual ===== -->
            <form class="brutal bg-paper p-5" @submit.prevent="simpan">
                <h2 class="font-display text-lg">Penyesuaian Manual</h2>
                <p class="mt-1 text-xs text-muted">
                    Untuk hal yang tidak tercatat sistem — menjadi pemateri, membantu kepanitiaan, atau koreksi.
                    Wajib beralasan, dan poin negatif diperbolehkan.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Anggota *</span>
                        <select v-model="form.member_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— Pilih anggota —</option>
                            <option v-for="a in props.pilihanAnggota" :key="a.id" :value="a.id">
                                {{ a.nama }} ({{ a.total }} poin)
                            </option>
                        </select>
                        <span v-if="form.errors.member_id" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.member_id }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Poin *</span>
                        <input v-model.number="form.poin" type="number" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span class="text-[11px] text-muted">Maks ±{{ props.batasPenyesuaian }} sekali beri.</span>
                        <span v-if="form.errors.poin" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.poin }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Periode</span>
                        <input v-model="form.periode" type="month" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block sm:col-span-4">
                        <span class="text-xs font-bold uppercase text-muted">Alasan *</span>
                        <input v-model="form.alasan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.alasan }}</span>
                    </label>
                </div>

                <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800 disabled:opacity-40">
                    Catat Penyesuaian
                </button>
            </form>

            <!-- ===== Buku besar ===== -->
            <section>
                <h2 class="font-display text-lg">Buku Besar Poin</h2>

                <p v-if="!props.catatan.length" class="mt-2 text-sm text-muted">Belum ada catatan poin untuk saringan ini.</p>

                <table v-else class="mt-3 w-full text-sm">
                    <thead class="border-b-2 border-ink text-left text-xs uppercase text-muted">
                        <tr>
                            <th class="py-2">Tanggal</th>
                            <th class="py-2">Anggota</th>
                            <th class="py-2">Sumber</th>
                            <th class="py-2">Keterangan</th>
                            <th class="py-2 text-right">Poin</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in props.catatan" :key="c.id" class="border-b-2 border-ink/10" :class="c.dibatalkan ? 'opacity-60' : ''">
                            <td class="py-2 text-xs">
                                {{ c.terjadi_pada ?? '—' }}
                                <span class="block text-[10px] text-muted">{{ c.periode }}</span>
                            </td>
                            <td class="py-2 font-bold">{{ c.anggota }}</td>
                            <td class="py-2 text-xs">{{ c.label_sumber }}</td>
                            <td class="py-2 text-xs">
                                {{ c.keterangan }}
                                <span v-if="c.pemberi" class="block text-[10px] text-muted">oleh {{ c.pemberi }}</span>
                                <span v-if="c.dibatalkan" class="block text-[10px] font-bold text-accent-600">
                                    Dibatalkan: {{ c.alasan_pembatalan }}<span v-if="c.pembatal"> ({{ c.pembatal }})</span>
                                </span>
                            </td>
                            <td class="py-2 text-right font-display" :class="c.dibatalkan ? 'line-through' : ''">
                                {{ c.poin > 0 ? `+${c.poin}` : c.poin }}
                            </td>
                            <td class="py-2 text-right">
                                <button
                                    v-if="!c.dibatalkan"
                                    type="button"
                                    class="brutal-sm bg-paper-alt px-2 py-1 text-xs font-bold"
                                    @click="batalUntuk = batalUntuk === c.id ? null : c.id"
                                >{{ batalUntuk === c.id ? 'Tutup' : 'Batalkan' }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <form
                    v-if="batalUntuk"
                    class="mt-3 border-t-2 border-ink/10 pt-3"
                    @submit.prevent="kirimBatal(props.catatan.find((x) => x.id === batalUntuk)!)"
                >
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Alasan pembatalan *</span>
                        <textarea v-model="formBatal.alasan" rows="2" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"></textarea>
                        <span class="text-[11px] text-muted">Barisnya tidak dihapus — hanya tidak lagi dihitung.</span>
                        <span v-if="formBatal.errors.alasan" class="mt-1 block text-xs font-bold text-accent-600">{{ formBatal.errors.alasan }}</span>
                    </label>

                    <div class="mt-2 flex gap-2">
                        <button type="submit" :disabled="formBatal.processing" class="brutal-sm bg-paper px-3 py-1.5 text-xs font-bold">Batalkan Poin</button>
                        <button type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="batalUntuk = null">Tutup</button>
                    </div>
                </form>
            </section>
        </div>
    </PanelLayout>
</template>
