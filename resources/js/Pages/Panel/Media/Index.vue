<script setup lang="ts">
/*
 * Pustaka Media: unggah berkas, telusuri, ubah keterangan, salin tautan, hapus.
 * Thumbnail hanya tersedia bila server memiliki ekstensi GD/Imagick.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Berkas {
    id: number;
    nama: string;
    nama_berkas: string;
    koleksi: string;
    label_koleksi: string;
    mime: string;
    gambar: boolean;
    alt: string;
    ukuran: string;
    url: string;
    diunggah_pada: string;
    dipakai: string[];
}

const props = defineProps<{
    daftar: {
        data: Berkas[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    saring: { koleksi: string; tipe: string; cari: string };
    pilihanKoleksi: Record<string, string>;
    batasMb: number;
    totalUkuran: string;
}>();

/* ===== Unggah berkas ===== */
const form = useForm<{ berkas: File[]; koleksi: string; alt: string }>({
    berkas: [],
    koleksi: 'gambar',
    alt: '',
});

const namaBerkasTerpilih = computed(() => form.berkas.map((b) => b.name).join(', '));

function pilihBerkas(peristiwa: Event) {
    const daftar = (peristiwa.target as HTMLInputElement).files;
    form.berkas = daftar ? Array.from(daftar) : [];
}

function unggah() {
    form.post('/panel/media', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('berkas', 'alt'),
    });
}

/* ===== Saringan ===== */
const cariLokal = ref(props.saring.cari);

function saringUlang(tambahan: Record<string, string>) {
    router.get(
        '/panel/media',
        { ...props.saring, cari: cariLokal.value, ...tambahan },
        { preserveState: true, preserveScroll: true },
    );
}

/* ===== Ubah & hapus ===== */
const sedangDisunting = ref<Berkas | null>(null);
const formSunting = useForm<{ name: string; alt: string; collection_name: string }>({
    name: '',
    alt: '',
    collection_name: 'gambar',
});

function bukaSunting(berkas: Berkas) {
    sedangDisunting.value = berkas;
    formSunting.name = berkas.nama;
    formSunting.alt = berkas.alt;
    formSunting.collection_name = berkas.koleksi;
    formSunting.clearErrors();
}

function simpanSunting() {
    if (!sedangDisunting.value) {
        return;
    }

    formSunting.patch(`/panel/media/${sedangDisunting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (sedangDisunting.value = null),
    });
}

function hapus(berkas: Berkas) {
    const dipakai = berkas.dipakai.length
        ? `\n\nPerhatian: berkas ini sedang dipakai oleh ${berkas.dipakai.join(', ')}.`
        : '';

    if (!confirm(`Hapus berkas "${berkas.nama_berkas}"?${dipakai}`)) {
        return;
    }

    router.delete(`/panel/media/${berkas.id}`, { preserveScroll: true });
}

/* ===== Salin tautan ===== */
const tersalin = ref<number | null>(null);
const tautanManual = ref<number | null>(null);
const alamatManual = ref('');

async function salinTautan(berkas: Berkas) {
    const alamat = new URL(berkas.url, window.location.origin).toString();

    try {
        await navigator.clipboard.writeText(alamat);
        tersalin.value = berkas.id;
        tautanManual.value = null;
        window.setTimeout(() => (tersalin.value = null), 2000);
    } catch {
        // Clipboard diblokir (konteks tidak aman / izin ditolak). Alamatnya
        // ditampilkan di kotak teks agar masih bisa disalin manual — JANGAN
        // memakai window.prompt(), yang di sandbox justru ikut diblokir
        // sehingga tombolnya tampak tidak berfungsi.
        tautanManual.value = berkas.id;
        alamatManual.value = alamat;
    }
}
</script>

<template>
    <PanelLayout>
        <Head title="Pustaka Media" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Pustaka Media</h1>
                    <p class="mt-1 text-sm text-muted">
                        {{ daftar.total }} berkas · total {{ totalUkuran }}
                    </p>
                </div>
            </div>

            <PesanHasil />

            <!-- ===== Unggah ===== -->
            <form class="brutal space-y-4 bg-paper p-5" @submit.prevent="unggah">
                <h2 class="font-display text-lg">Unggah Berkas</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="berkas" class="block text-sm font-bold">Berkas (bisa beberapa sekaligus)</label>
                        <input
                            id="berkas"
                            type="file"
                            multiple
                            class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                            @change="pilihBerkas"
                        >
                        <p class="mt-1 text-xs text-muted">
                            Maksimal {{ batasMb }} MB per berkas. Gambar (JPG, PNG, WebP, GIF) atau dokumen (PDF, DOC, XLS, PPT).
                        </p>
                        <p v-if="namaBerkasTerpilih" class="mt-1 truncate text-xs font-semibold">{{ namaBerkasTerpilih }}</p>
                    </div>

                    <div>
                        <label for="koleksi" class="block text-sm font-bold">Koleksi</label>
                        <select id="koleksi" v-model="form.koleksi" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in pilihanKoleksi" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>

                        <label for="alt" class="mt-3 block text-sm font-bold">Teks alternatif (opsional)</label>
                        <input
                            id="alt"
                            v-model="form.alt"
                            type="text"
                            maxlength="190"
                            placeholder="Deskripsi singkat untuk pembaca layar"
                            class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm"
                        >
                    </div>
                </div>

                <button
                    type="submit"
                    class="brutal-sm brutal-hover bg-primary-600 px-5 py-2.5 text-sm font-bold text-paper disabled:opacity-50"
                    :disabled="form.processing || !form.berkas.length"
                >
                    {{ form.processing ? 'Mengunggah…' : 'Unggah' }}
                </button>
            </form>

            <!-- ===== Saringan ===== -->
            <div class="flex flex-wrap items-center gap-2">
                <select
                    :value="saring.koleksi"
                    class="brutal-sm bg-paper px-3 py-1.5 text-sm font-bold"
                    @change="saringUlang({ koleksi: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="semua">Semua koleksi</option>
                    <option v-for="(label, nilai) in pilihanKoleksi" :key="nilai" :value="nilai">{{ label }}</option>
                </select>

                <select
                    :value="saring.tipe"
                    class="brutal-sm bg-paper px-3 py-1.5 text-sm font-bold"
                    @change="saringUlang({ tipe: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="semua">Semua jenis</option>
                    <option value="gambar">Gambar</option>
                    <option value="dokumen">Dokumen</option>
                </select>

                <form class="ml-auto flex gap-2" @submit.prevent="saringUlang({})">
                    <input
                        v-model="cariLokal"
                        type="search"
                        placeholder="Cari nama berkas…"
                        class="brutal-sm w-56 bg-paper px-3 py-1.5 text-sm"
                    >
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-3 py-1.5 text-sm font-bold text-paper">
                        Cari
                    </button>
                </form>
            </div>

            <!-- ===== Galeri ===== -->
            <div v-if="!daftar.data.length" class="brutal bg-paper-alt px-4 py-10 text-center text-sm text-muted">
                Belum ada berkas. Unggah berkas pertama lewat formulir di atas.
            </div>

            <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="berkas in daftar.data" :key="berkas.id" class="brutal flex flex-col bg-paper">
                    <div class="grid h-40 place-items-center border-b-2 border-ink bg-paper-alt p-2">
                        <img
                            v-if="berkas.gambar"
                            :src="berkas.url"
                            :alt="berkas.alt || berkas.nama"
                            loading="lazy"
                            class="max-h-36 w-auto object-contain"
                        >
                        <span v-else class="text-xs font-bold uppercase text-muted">
                            {{ berkas.mime?.split('/').pop() }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col p-3">
                        <p class="truncate text-sm font-bold" :title="berkas.nama_berkas">{{ berkas.nama }}</p>
                        <p class="mt-0.5 truncate text-xs text-muted">{{ berkas.nama_berkas }} · {{ berkas.ukuran }}</p>

                        <div class="mt-2 flex flex-wrap gap-1">
                            <span class="border-2 border-ink bg-accent-100 px-1.5 text-[10px] font-bold uppercase">
                                {{ berkas.label_koleksi }}
                            </span>
                            <span v-if="berkas.alt" class="truncate border-2 border-ink/30 px-1.5 text-[10px] text-muted" :title="berkas.alt">
                                alt: {{ berkas.alt }}
                            </span>
                        </div>

                        <p v-if="berkas.dipakai.length" class="mt-2 text-[11px] font-semibold text-muted">
                            Dipakai: {{ berkas.dipakai.join(', ') }}
                        </p>

                        <div class="mt-auto flex flex-wrap gap-1 pt-3">
                            <button type="button" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold" @click="bukaSunting(berkas)">
                                Ubah
                            </button>
                            <button type="button" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold" @click="salinTautan(berkas)">
                                {{ tersalin === berkas.id ? 'Tersalin ✓' : 'Salin tautan' }}
                            </button>
                            <a :href="berkas.url" target="_blank" rel="noopener noreferrer" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold">
                                Buka
                            </a>
                            <button type="button" class="brutal-sm brutal-hover bg-accent-100 px-2 py-1 text-xs font-bold" @click="hapus(berkas)">
                                Hapus
                            </button>
                        </div>

                        <div v-if="tautanManual === berkas.id" class="mt-2">
                            <label class="text-[11px] font-bold uppercase text-muted">Salin tautan ini</label>
                            <input
                                :value="alamatManual"
                                readonly
                                class="mt-0.5 w-full border-2 border-ink bg-paper-alt px-2 py-1 text-[11px]"
                                @click="($event.target as HTMLInputElement).select()"
                            >
                        </div>
                    </div>
                </li>
            </ul>

            <Penomoran :tautan="daftar.links" />
        </div>

        <!-- ===== Modal ubah keterangan ===== -->
        <div
            v-if="sedangDisunting"
            class="fixed inset-0 z-50 grid place-items-center bg-ink/60 p-4"
            @click.self="sedangDisunting = null"
        >
            <form class="brutal w-full max-w-lg bg-paper p-5" @submit.prevent="simpanSunting">
                <h2 class="font-display text-lg">Ubah Keterangan Berkas</h2>
                <p class="mt-1 truncate text-xs text-muted">{{ sedangDisunting.nama_berkas }}</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="nama-berkas" class="block text-sm font-bold">Nama tampilan</label>
                        <input id="nama-berkas" v-model="formSunting.name" type="text" maxlength="190" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="alt-berkas" class="block text-sm font-bold">Teks alternatif</label>
                        <input id="alt-berkas" v-model="formSunting.alt" type="text" maxlength="190" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="koleksi-berkas" class="block text-sm font-bold">Koleksi</label>
                        <select id="koleksi-berkas" v-model="formSunting.collection_name" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                            <option v-for="(label, nilai) in pilihanKoleksi" :key="nilai" :value="nilai">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="sedangDisunting = null">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formSunting.processing">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
