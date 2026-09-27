<script setup lang="ts">
/*
 * Isi pusat notifikasi — dipakai bersama panel dan area anggota.
 *
 * SATU HAL YANG PALING PENTING DI SINI: penanda baca.
 *
 * Notifikasi yang BELUM dibaca harus bisa dikenali tanpa harus membaca satu
 * per satu — kalau tidak, seluruh daftar harus dibaca ulang setiap kali dibuka
 * dan lama-lama orang berhenti membukanya. Karena itu yang belum dibaca diberi
 * garis tebal di kiri dan latar berbeda, bukan sekadar tanggal yang lebih gelap.
 *
 * Preferensi email ADA DI HALAMAN YANG SAMA, bukan di halaman pengaturan
 * terpisah: orang biasanya baru sadar ingin mengubah pengaturan saat sedang
 * melihat notifikasinya, dan memindahkannya ke tempat lain berarti ia harus
 * mencari lagi.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Notifikasi {
    id: string;
    kategori: string;
    label_kategori: string;
    ikon: string;
    judul: string;
    pesan: string;
    tautan: string | null;
    dibaca: boolean;
    waktu: string | null;
}

interface Preferensi {
    kunci: string;
    label: string;
    keterangan: string;
    ikon: string;
    wajib: boolean;
    email: boolean;
}

const props = defineProps<{
    daftar: Notifikasi[];
    kategori: string;
    pilihanKategori: Record<string, string>;
    jumlahPerKategori: Record<string, number>;
    belumDibaca: number;
    preferensi: Preferensi[];
    catatan: string;
}>();

const kategoriSaring = ref(props.kategori);
const bukaPengaturan = ref(false);

const isian = useForm<{ kategori: Record<string, boolean> }>({
    kategori: Object.fromEntries(props.preferensi.map((p) => [p.kunci, p.email])),
});

/** Kategori yang benar-benar bisa dimatikan — sisanya ditampilkan terkunci. */
const bisaDiatur = computed(() => props.preferensi.filter((p) => !p.wajib));
const terkunci = computed(() => props.preferensi.filter((p) => p.wajib));

function saring(): void {
    router.get('/notifikasi', { kategori: kategoriSaring.value }, { preserveScroll: true });
}

function baca(n: Notifikasi): void {
    if (n.dibaca) {
        return;
    }

    router.post(`/notifikasi/${n.id}/baca`, {}, { preserveScroll: true, preserveState: true });
}

/**
 * Buka notifikasi: TANDAI DULU, baru pindah halaman.
 *
 * Sempat ditulis sebagai tautan biasa yang sekaligus menembak permintaan
 * penanda baca. Itu tidak bekerja: berpindah halaman membatalkan permintaannya,
 * sehingga notifikasi yang baru saja dibuka tetap terlihat belum dibaca.
 */
function buka(n: Notifikasi): void {
    if (n.tautan === null) {
        return;
    }

    const tujuan = n.tautan;

    if (n.dibaca) {
        window.location.href = tujuan;

        return;
    }

    router.post(
        `/notifikasi/${n.id}/baca`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                window.location.href = tujuan;
            },
        },
    );
}

function tandaiSemua(): void {
    router.post('/notifikasi/baca-semua', {}, { preserveScroll: true, preserveState: true });
}

function simpanPreferensi(): void {
    isian.post('/notifikasi/preferensi', { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <PesanHasil />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl">Notifikasi</h1>
                <p class="text-sm text-muted">
                    <template v-if="props.belumDibaca > 0">{{ props.belumDibaca }} belum dibaca.</template>
                    <template v-else>Semua sudah dibaca.</template>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="props.belumDibaca > 0"
                    type="button"
                    class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800"
                    @click="tandaiSemua"
                >Tandai Semua Dibaca</button>

                <button
                    type="button"
                    class="brutal-sm bg-paper-alt px-4 py-2 text-sm font-bold"
                    @click="bukaPengaturan = !bukaPengaturan"
                >{{ bukaPengaturan ? 'Tutup Preferensi' : 'Preferensi Email' }}</button>
            </div>
        </div>

        <p class="brutal bg-paper-alt p-4 text-xs leading-relaxed">{{ props.catatan }}</p>

        <!-- ===== Preferensi email ===== -->
        <section v-if="bukaPengaturan" class="brutal bg-paper p-5">
            <h2 class="font-display text-lg">Preferensi Email</h2>
            <p class="mt-1 text-xs text-muted">
                Notifikasi di halaman ini tetap aktif untuk semua kategori, apa pun pilihan di bawah. Yang kamu atur
                di sini hanya apakah kategorinya juga dikirim ke emailmu.
            </p>

            <form class="mt-4 space-y-3" @submit.prevent="simpanPreferensi">
                <div v-for="p in bisaDiatur" :key="p.kunci" class="border-2 border-ink/15 p-3">
                    <label class="flex items-start gap-3">
                        <input v-model="isian.kategori[p.kunci]" type="checkbox" class="mt-1 h-4 w-4 border-2 border-ink">
                        <span class="min-w-0">
                            <span class="block font-bold">{{ p.ikon }} {{ p.label }}</span>
                            <span class="block text-xs text-muted">{{ p.keterangan }}</span>
                        </span>
                    </label>
                </div>

                <div v-for="p in terkunci" :key="p.kunci" class="border-2 border-dashed border-ink/25 bg-paper-alt p-3">
                    <p class="font-bold">{{ p.ikon }} {{ p.label }} <span class="ml-1 border-2 border-ink bg-accent-400 px-1.5 py-0.5 text-[10px] uppercase">Selalu dikirim</span></p>
                    <p class="mt-1 text-xs text-muted">{{ p.keterangan }}</p>
                </div>

                <button type="submit" :disabled="isian.processing" class="brutal-sm brutal-hover bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    Simpan Preferensi
                </button>
            </form>
        </section>

        <!-- ===== Saringan ===== -->
        <div class="flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-xs font-bold uppercase text-muted">Kategori</span>
                <select v-model="kategoriSaring" class="brutal-sm mt-1 bg-paper-alt px-3 py-2 text-sm" @change="saring">
                    <option value="">Semua kategori</option>
                    <option v-for="(label, kunci) in props.pilihanKategori" :key="kunci" :value="kunci">
                        {{ label }}<template v-if="props.jumlahPerKategori[kunci]"> ({{ props.jumlahPerKategori[kunci] }})</template>
                    </option>
                </select>
            </label>
        </div>

        <p v-if="!props.daftar.length" class="brutal bg-paper p-6">
            <span class="font-display text-lg">Belum ada notifikasi.</span>
            <span class="mt-1 block text-sm text-muted">Kabar tentang keanggotaan, peminjaman, publikasi, dan kegiatan akan muncul di sini.</span>
        </p>

        <ul v-else class="space-y-3">
            <li
                v-for="n in props.daftar"
                :key="n.id"
                class="brutal bg-paper p-5"
                :class="n.dibaca ? '' : 'border-l-8 border-l-accent-400'"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted">
                            {{ n.ikon }} {{ n.label_kategori }}
                            <span v-if="!n.dibaca" class="ml-1 border-2 border-ink bg-accent-400 px-1.5 py-0.5">Baru</span>
                        </p>
                        <h2 class="mt-1 font-display text-base leading-tight">{{ n.judul }}</h2>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ n.pesan }}</p>
                        <p class="mt-2 text-xs text-muted">{{ n.waktu }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-if="n.tautan"
                        type="button"
                        class="brutal-sm brutal-hover bg-accent-400 px-3 py-1.5 text-xs font-bold text-primary-800"
                        @click="buka(n)"
                    >Buka</button>

                    <button
                        v-if="!n.dibaca"
                        type="button"
                        class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                        @click="baca(n)"
                    >Tandai Dibaca</button>
                </div>
            </li>
        </ul>
    </div>
</template>
