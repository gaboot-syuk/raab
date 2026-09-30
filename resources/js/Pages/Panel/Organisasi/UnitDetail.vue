<script setup lang="ts">
/*
 * Kelola satu unit: pengurus, anggota, dan agenda (khusus LSO).
 *
 * Sebelumnya ketiganya tersebar di halaman Penugasan, Keanggotaan, dan Galeri,
 * sehingga merapikan satu LSO berarti berpindah tiga halaman sambil mengingat
 * nama unitnya. Halaman ini mengumpulkannya di satu tempat.
 *
 * Semua aksi memakai endpoint yang SUDAH ADA untuk jabatan, penugasan, dan
 * agenda; hanya keanggotaan unit yang punya endpoint sendiri di sini.
 */
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { useIzin } from '@/composables/useIzin';
import PanelLayout from '@/Layouts/PanelLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Unit {
    id: number;
    jenis: string;
    label_jenis: string;
    nama: string;
    slug: string | null;
    singkatan: string | null;
    deskripsi: Record<string, string> | null;
    warna: string | null;
    urutan: number;
    aktif: boolean;
    jumlah_jabatan: number;
    jumlah_anggota: number;
    jumlah_galeri: number;
    tautan_publik: string | null;
    lso: boolean;
}

interface Jabatan {
    id: number;
    nama: string;
    level: number;
    aktif: boolean;
}

interface Pengurus {
    id: number;
    position_id: number;
    jabatan: string | null;
    nama: string;
    nomor_anggota: string | null;
    manual: boolean;
    keterangan: string | null;
    aktif: boolean;
}

interface Anggota {
    id: number;
    nama: string;
    nomor_anggota: string | null;
    status: string;
    label_status: string;
}

interface Agenda {
    id: number;
    judul: Record<string, string> | null;
    mulai: string | null;
    selesai: string | null;
    lokasi: string | null;
    publik: boolean;
    gambar_media_id: number | null;
    gambar: string | null;
}

interface Pilihan {
    id: number;
    label: string;
    unit_id: number | null;
}

interface PilihanGambar {
    id: number;
    nama: string;
    url: string;
}

const props = defineProps<{
    unit: Unit;
    periode: { id: number; nama: string; aktif: boolean } | null;
    jabatan: Jabatan[];
    pengurus: Pengurus[];
    anggota: Anggota[];
    agenda: Agenda[];
    pilihanAnggota: Pilihan[];
    pilihanGambar: PilihanGambar[];
}>();

const kelola = ref<'profil' | 'pengurus' | 'anggota' | 'agenda'>('profil');

/*
 * Satu halaman, beberapa izin.
 *
 * Sejak Konten Manager boleh membuka halaman ini (units.view + units.update),
 * pengunjung halaman ini TIDAK lagi selalu orang yang berhak atas semua
 * aksinya. Tombol dan formulir yang pasti ditolak server disembunyikan, supaya
 * tidak ada yang menekan tombol lalu hanya melihat modal galat tanpa
 * penjelasan.
 *
 * Pembagiannya: profil unit → units.update · jabatan → positions.manage ·
 * pengurus → assignments.manage · anggota → members.update ·
 * agenda → unit-agendas.manage.
 */
const { boleh } = useIzin();

/* ------------------------------- Profil ------------------------------- */

const form = useForm({
    jenis: props.unit.jenis,
    nama: props.unit.nama,
    singkatan: props.unit.singkatan ?? '',
    deskripsi: { id: props.unit.deskripsi?.id ?? '', en: props.unit.deskripsi?.en ?? '' },
    warna: props.unit.warna ?? '',
    urutan: props.unit.urutan,
    aktif: props.unit.aktif,
});

function simpanProfil(): void {
    form.put(`/panel/organisasi/unit/${props.unit.id}`, { preserveScroll: true });
}

/* ------------------------------ Pengurus ------------------------------ */

const formJabatan = useForm({
    nama: '',
    level: 2,
    urutan: 0,
    unit_id: props.unit.id,
    rangkap_diizinkan: false,
    aktif: true,
});

const formPengurus = useForm({
    period_id: props.periode?.id ?? null,
    position_id: null as number | null,
    member_id: null as number | null,
    nama_manual: '',
    keterangan: '',
    urutan: 0,
    aktif: true,
});

function simpanJabatan(): void {
    formJabatan.post('/panel/organisasi/jabatan', {
        preserveScroll: true,
        onSuccess: () => formJabatan.reset('nama'),
    });
}

function tambahPengurus(): void {
    if (!formPengurus.position_id) {
        formPengurus.setError('position_id', 'Pilih jabatannya lebih dulu.');

        return;
    }

    formPengurus.post('/panel/organisasi/penugasan', {
        preserveScroll: true,
        onSuccess: () => {
            formPengurus.reset('member_id', 'nama_manual', 'keterangan');
        },
    });
}

function lepasPengurus(baris: Pengurus): void {
    if (!confirm(`Lepas ${baris.nama} dari jabatan ${baris.jabatan}?`)) {
        return;
    }

    router.delete(`/panel/organisasi/penugasan/${baris.id}`, { preserveScroll: true });
}

/* ------------------------------- Anggota ------------------------------- */

const formAnggota = useForm({ member_id: null as number | null });

function tambahAnggota(): void {
    if (!formAnggota.member_id) {
        formAnggota.setError('member_id', 'Pilih anggotanya lebih dulu.');

        return;
    }

    formAnggota.post(`/panel/organisasi/unit/${props.unit.id}/anggota`, {
        preserveScroll: true,
        onSuccess: () => formAnggota.reset('member_id'),
    });
}

function lepasAnggota(orang: Anggota): void {
    if (!confirm(`Lepas ${orang.nama} dari ${props.unit.nama}? Anggotanya tidak dihapus.`)) {
        return;
    }

    router.delete(`/panel/organisasi/unit/${props.unit.id}/anggota/${orang.id}`, { preserveScroll: true });
}

/* -------------------------------- Agenda ------------------------------- */

const formAgenda = useForm({
    unit_id: props.unit.id,
    judul: { id: '', en: '' } as Record<string, string>,
    deskripsi: { id: '', en: '' } as Record<string, string>,
    mulai: '',
    selesai: '',
    lokasi: '',
    publik: true,
    gambar_media_id: null as number | null,
});

function simpanAgenda(): void {
    formAgenda.post('/panel/organisasi/agenda', {
        preserveScroll: true,
        onSuccess: () => {
            formAgenda.reset('judul', 'deskripsi', 'mulai', 'selesai', 'lokasi', 'gambar_media_id');
        },
    });
}

/** Pratinjau gambar yang sedang dipilih, supaya salah pilih langsung terlihat. */
const pratinjauGambar = computed(
    () => props.pilihanGambar.find((g) => g.id === formAgenda.gambar_media_id)?.url ?? null,
);

function hapusAgenda(item: Agenda): void {
    if (!confirm(`Hapus agenda "${item.judul?.id}"?`)) {
        return;
    }

    router.delete(`/panel/organisasi/agenda/${item.id}`, { preserveScroll: true });
}

/* -------------------------------- Bantuan ------------------------------ */

/** Anggota yang sudah berada di unit ini tidak ditawarkan lagi. */
const pilihanBelumAnggota = computed(() =>
    props.pilihanAnggota.filter((p) => p.unit_id !== props.unit.id),
);

function pindahTab(tab: typeof kelola.value): void {
    kelola.value = tab;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <PanelLayout>
        <Head :title="`Kelola ${unit.nama}`" />

        <PesanHasil />

        <div class="mx-auto max-w-5xl space-y-6">
            <!-- Kepala -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/panel/organisasi/unit" class="text-xs font-bold uppercase text-muted hover:underline">
                        &larr; Biro &amp; LSO
                    </Link>
                    <h1 class="mt-1 font-display text-2xl sm:text-3xl">{{ unit.nama }}</h1>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                        <span class="brutal-sm bg-paper-alt px-2 py-0.5 text-[10px] uppercase">{{ unit.label_jenis }}</span>
                        <span>{{ unit.jumlah_jabatan }} jabatan · {{ unit.jumlah_anggota }} anggota · {{ unit.jumlah_galeri }} album</span>
                        <span v-if="!unit.aktif" class="font-bold">(nonaktif)</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="unit.tautan_publik"
                        :href="unit.tautan_publik"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold"
                    >Halaman Publik</a>
                    <Link href="/panel/organisasi/galeri" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold">
                        Galeri &amp; Album
                    </Link>
                </div>
            </div>

            <!-- Tab -->
            <nav class="flex flex-wrap gap-2" aria-label="Bagian pengelolaan unit">
                <button
                    v-for="tab in (['profil', 'pengurus', 'anggota'] as const)"
                    :key="tab"
                    type="button"
                    class="brutal-sm px-3 py-1.5 text-sm font-bold capitalize"
                    :class="kelola === tab ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                    @click="pindahTab(tab)"
                >{{ tab }}</button>
                <button
                    v-if="unit.lso"
                    type="button"
                    class="brutal-sm px-3 py-1.5 text-sm font-bold capitalize"
                    :class="kelola === 'agenda' ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                    @click="pindahTab('agenda')"
                >Agenda</button>
            </nav>

            <p v-if="!unit.lso" class="brutal bg-paper-alt p-4 text-sm text-muted">
                Agenda hanya untuk Lembaga Semi Otonom. Biro adalah bagian struktural rayon, jadi tidak
                menyelenggarakan agenda sendiri.
            </p>

            <!-- ============================ PROFIL ============================ -->
            <form v-if="kelola === 'profil' && boleh('units.update')" class="brutal bg-paper p-5" @submit.prevent="simpanProfil">
                <h2 class="font-display text-lg">Profil Unit</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Jenis *</span>
                        <select v-model="form.jenis" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option value="biro">Biro</option>
                            <option value="lso">Lembaga Semi Otonom</option>
                        </select>
                        <span v-if="form.errors.jenis" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.jenis }}</span>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Nama unit *</span>
                        <input v-model="form.nama" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        <span v-if="form.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ form.errors.nama }}</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Singkatan</span>
                        <input v-model="form.singkatan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Warna (token)</span>
                        <input v-model="form.warna" type="text" placeholder="mis. accent-400" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase text-muted">Urutan</span>
                        <input v-model.number="form.urutan" type="number" min="0" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </label>

                    <label class="block sm:col-span-2 lg:col-span-1">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Indonesia)</span>
                        <textarea v-model="form.deskripsi.id" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-xs font-bold uppercase text-muted">Deskripsi (Inggris)</span>
                        <textarea v-model="form.deskripsi.en" rows="3" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm" />
                    </label>
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                    <input v-model="form.aktif" type="checkbox" class="h-4 w-4"> Aktif (tampil di situs)
                </label>

                <button type="submit" :disabled="form.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                    Simpan Perubahan
                </button>
            </form>

            <p v-else-if="kelola === 'profil'" class="brutal bg-paper-alt p-4 text-sm text-muted">
                Profil unit hanya dapat disunting oleh pengurus yang memegang izin penyuntingan unit.
                Isinya tetap bisa dibaca dari tab lain, dan tampilannya di situs dapat dilihat lewat
                tombol Halaman Publik di atas.
            </p>

            <!-- =========================== PENGURUS =========================== -->
            <section v-else-if="kelola === 'pengurus'" class="space-y-6">
                <div class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Pengurus Unit</h2>
                    <p class="mt-1 text-sm text-muted">
                        Kepengurusan diambil dari jabatan yang bertaut ke unit ini.
                        <template v-if="periode">
                            Periode: <strong>{{ periode.nama }}</strong>{{ periode.aktif ? ' (berjalan)' : '' }}.
                        </template>
                    </p>

                    <p v-if="!periode" class="brutal-sm mt-3 bg-paper-alt p-3 text-sm">
                        Belum ada periode kepengurusan. Buat dulu di
                        <Link href="/panel/organisasi/periode" class="font-bold underline">halaman Periode</Link>.
                    </p>

                    <p v-if="!jabatan.length" class="brutal-sm mt-3 bg-paper-alt p-3 text-sm text-muted">
                        Unit ini belum punya jabatan. Tambahkan jabatan pertama di formulir bawah.
                    </p>

                    <p v-else-if="!pengurus.length" class="mt-3 text-sm text-muted">Belum ada pengurus pada periode ini.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="baris in pengurus" :key="baris.id" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ baris.jabatan }}
                                    <span v-if="baris.manual" class="brutal-sm ml-2 bg-paper-alt px-2 py-0.5 text-[10px] uppercase">nama manual</span>
                                    <span v-if="!baris.aktif" class="ml-2 text-xs font-normal text-muted">(nonaktif)</span>
                                </p>
                                <p class="text-xs text-muted">
                                    {{ baris.nama }}<span v-if="baris.nomor_anggota"> · {{ baris.nomor_anggota }}</span>
                                    <span v-if="baris.keterangan"> · {{ baris.keterangan }}</span>
                                </p>
                            </div>
                            <button v-if="boleh('assignments.manage')" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="lepasPengurus(baris)">
                                Lepas
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Tambah jabatan unit -->
                <form v-if="boleh('positions.manage')" class="brutal bg-paper p-5" @submit.prevent="simpanJabatan">
                    <h2 class="font-display text-lg">Tambah Jabatan di Unit Ini</h2>
                    <p class="mt-1 text-sm text-muted">Jabatan inilah yang nanti diisi nama pengurusnya.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Nama jabatan *</span>
                            <input v-model="formJabatan.nama" type="text" placeholder="Ketua" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formJabatan.errors.nama" class="mt-1 block text-xs font-bold text-accent-600">{{ formJabatan.errors.nama }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Tingkat *</span>
                            <input v-model.number="formJabatan.level" type="number" min="1" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                    </div>

                    <label class="mt-3 flex items-center gap-2 text-sm font-bold">
                        <input v-model="formJabatan.rangkap_diizinkan" type="checkbox" class="h-4 w-4"> Boleh dirangkap satu orang
                    </label>

                    <button type="submit" :disabled="formJabatan.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambah Jabatan
                    </button>
                </form>

                <!-- Tunjuk pengurus -->
                <form v-if="jabatan.length && periode && boleh('assignments.manage')" class="brutal bg-paper p-5" @submit.prevent="tambahPengurus">
                    <h2 class="font-display text-lg">Tunjuk Pengurus</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Jabatan *</span>
                            <select v-model="formPengurus.position_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— pilih jabatan —</option>
                                <option v-for="j in jabatan" :key="j.id" :value="j.id">{{ j.nama }}</option>
                            </select>
                            <span v-if="formPengurus.errors.position_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formPengurus.errors.position_id }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Anggota terdaftar</span>
                            <select v-model="formPengurus.member_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— tidak ada / bukan anggota —</option>
                                <option v-for="p in pilihanAnggota" :key="p.id" :value="p.id">{{ p.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">atau Nama manual</span>
                            <input v-model="formPengurus.nama_manual" type="text" placeholder="mis. Dr. Ahmad, M.Pd." class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formPengurus.errors.nama_manual" class="mt-1 block text-xs font-bold text-accent-600">{{ formPengurus.errors.nama_manual }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Keterangan</span>
                            <input v-model="formPengurus.keterangan" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>
                    </div>

                    <p class="mt-3 text-xs text-muted">
                        Isi salah satu: anggota terdaftar atau nama manual. Pengurus boleh bukan anggota
                        (dosen pembina, tokoh, atau kader yang datanya belum masuk).
                    </p>

                    <button type="submit" :disabled="formPengurus.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tunjuk sebagai Pengurus
                    </button>
                </form>
            </section>

            <!-- =========================== ANGGOTA =========================== -->
            <section v-else-if="kelola === 'anggota'" class="space-y-6">
                <div class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Anggota Unit</h2>
                    <p class="mt-1 text-sm text-muted">
                        Satu anggota hanya berada di satu unit. Memindahkan anggota dari unit lain akan
                        mengganti unitnya, bukan menambah keanggotaan kedua.
                    </p>

                    <p v-if="!anggota.length" class="mt-3 text-sm text-muted">Belum ada anggota di unit ini.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="orang in anggota" :key="orang.id" class="flex flex-wrap items-center gap-3 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">{{ orang.nama }}</p>
                                <p class="text-xs text-muted">
                                    {{ orang.nomor_anggota ?? 'tanpa nomor anggota' }} · {{ orang.label_status }}
                                </p>
                            </div>
                            <button v-if="boleh('members.update')" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="lepasAnggota(orang)">
                                Lepas
                            </button>
                        </li>
                    </ul>
                </div>

                <form v-if="boleh('members.update')" class="brutal bg-paper p-5" @submit.prevent="tambahAnggota">
                    <h2 class="font-display text-lg">Tambah Anggota</h2>

                    <label class="mt-4 block">
                        <span class="text-xs font-bold uppercase text-muted">Anggota *</span>
                        <select v-model="formAnggota.member_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option :value="null">— pilih anggota —</option>
                            <option v-for="p in pilihanBelumAnggota" :key="p.id" :value="p.id">
                                {{ p.label }}{{ p.unit_id ? ' · sedang di unit lain' : '' }}
                            </option>
                        </select>
                        <span v-if="formAnggota.errors.member_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formAnggota.errors.member_id }}</span>
                    </label>

                    <button type="submit" :disabled="formAnggota.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambahkan ke Unit
                    </button>
                </form>
            </section>

            <!-- ============================ AGENDA =========================== -->
            <section v-else-if="kelola === 'agenda' && unit.lso" class="space-y-6">
                <div class="brutal bg-paper p-5">
                    <h2 class="font-display text-lg">Agenda {{ unit.nama }}</h2>

                    <p v-if="!agenda.length" class="mt-3 text-sm text-muted">Belum ada agenda untuk unit ini.</p>

                    <ul v-else class="mt-3 divide-y-2 divide-ink/10">
                        <li v-for="item in agenda" :key="item.id" class="flex flex-wrap items-center gap-3 py-3">
                            <img v-if="item.gambar" :src="item.gambar" alt="" class="h-14 w-14 shrink-0 border-2 border-ink object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">
                                    {{ item.judul?.id }}
                                    <span v-if="!item.publik" class="ml-2 text-xs font-normal text-muted">(draf)</span>
                                </p>
                                <p class="text-xs text-muted">
                                    {{ (item.mulai ?? '').replace('T', ' ') }}<span v-if="item.selesai"> s.d. {{ item.selesai.replace('T', ' ') }}</span>
                                    <span v-if="item.lokasi"> · {{ item.lokasi }}</span>
                                </p>
                            </div>
                            <button v-if="boleh('unit-agendas.manage')" type="button" class="brutal-sm bg-paper-alt px-3 py-1.5 text-xs font-bold" @click="hapusAgenda(item)">
                                Hapus
                            </button>
                        </li>
                    </ul>
                </div>

                <form v-if="boleh('unit-agendas.manage')" class="brutal bg-paper p-5" @submit.prevent="simpanAgenda">
                    <h2 class="font-display text-lg">Tambah Agenda</h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Indonesia) *</span>
                            <input v-model="formAgenda.judul.id" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAgenda.errors['judul.id']" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors['judul.id'] }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Judul (Inggris)</span>
                            <input v-model="formAgenda.judul.en" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Mulai *</span>
                            <input v-model="formAgenda.mulai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <span v-if="formAgenda.errors.mulai" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors.mulai }}</span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase text-muted">Selesai</span>
                            <input v-model="formAgenda.selesai" type="datetime-local" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Lokasi</span>
                            <input v-model="formAgenda.lokasi" type="text" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </label>

                        <label class="block sm:col-span-2">
                            <span class="text-xs font-bold uppercase text-muted">Gambar (opsional)</span>
                            <select v-model="formAgenda.gambar_media_id" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                                <option :value="null">— tanpa gambar —</option>
                                <option v-for="g in pilihanGambar" :key="g.id" :value="g.id">{{ g.nama }}</option>
                            </select>
                            <span v-if="formAgenda.errors.gambar_media_id" class="mt-1 block text-xs font-bold text-accent-600">{{ formAgenda.errors.gambar_media_id }}</span>
                            <span v-if="!pilihanGambar.length" class="mt-1 block text-xs text-muted">
                                Pustaka media masih kosong. Unggah gambar dulu di menu Pustaka Media.
                            </span>
                            <img v-if="pratinjauGambar" :src="pratinjauGambar" alt="" class="brutal-sm mt-2 h-28 w-full object-cover">
                        </label>
                    </div>

                    <label class="mt-4 flex items-center gap-2 text-sm font-bold">
                        <input v-model="formAgenda.publik" type="checkbox" class="h-4 w-4"> Tampilkan di halaman publik
                    </label>

                    <button type="submit" :disabled="formAgenda.processing" class="brutal-sm brutal-hover mt-4 bg-accent-400 px-4 py-2 text-sm font-bold text-primary-800">
                        Tambah Agenda
                    </button>
                </form>
            </section>
        </div>
    </PanelLayout>
</template>
