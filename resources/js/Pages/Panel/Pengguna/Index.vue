<script setup lang="ts">
/*
 * Akun pengurus: membuat akun, menetapkan peran, mengubah, dan menghapus.
 * Hanya Superadmin yang dapat membuka halaman ini.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import Penomoran from '@/Components/Panel/Penomoran.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Akun {
    id: number;
    nama: string;
    email: string;
    peran: string[];
    terverifikasi: boolean;
    dibuat_pada: string;
    adalah_saya: boolean;
}

const props = defineProps<{
    daftar: {
        data: Akun[];
        links: { url: string | null; label: string; aktif: boolean }[];
        total: number;
    };
    cari: string;
    semuaPeran: string[];
}>();

const halaman = usePage<{ auth: { user: { id: number } | null } }>();
const idSaya = computed(() => halaman.props.auth?.user?.id ?? 0);

const labelPeran: Record<string, string> = {
    superadmin: 'Superadmin',
    sekretaris: 'Sekretaris',
    bendahara: 'Bendahara',
    konten_manager: 'Konten Manager',
};

const cariLokal = ref(props.cari);

function cari() {
    router.get('/panel/pengguna', { cari: cariLokal.value }, { preserveState: true, preserveScroll: true });
}

/* ===== Formulir tambah ===== */
const formTambah = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    peran: ['sekretaris'] as string[],
    verifikasi_sekarang: true,
});

const modalTambah = ref(false);

function simpanTambah() {
    formTambah.post('/panel/pengguna', {
        preserveScroll: true,
        onSuccess: () => {
            modalTambah.value = false;
            formTambah.reset();
        },
    });
}

/* ===== Formulir ubah ===== */
const modalUbah = ref(false);
const akunDisunting = ref<Akun | null>(null);

const formUbah = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    peran: [] as string[],
    verifikasi_email: true,
});

function bukaUbah(akun: Akun) {
    akunDisunting.value = akun;
    formUbah.name = akun.nama;
    formUbah.email = akun.email;
    formUbah.password = '';
    formUbah.password_confirmation = '';
    formUbah.peran = [...akun.peran];
    formUbah.verifikasi_email = akun.terverifikasi;
    formUbah.clearErrors();
    modalUbah.value = true;
}

function simpanUbah() {
    if (!akunDisunting.value) {
        return;
    }

    formUbah.put(`/panel/pengguna/${akunDisunting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (modalUbah.value = false),
    });
}

function hapus(akun: Akun) {
    if (!confirm(`Hapus akun ${akun.nama} (${akun.email})? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    router.delete(`/panel/pengguna/${akun.id}`, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head title="Akun Pengurus" />

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl sm:text-3xl">Akun Pengurus</h1>
                    <p class="mt-1 text-sm text-muted">
                        {{ daftar.total }} akun. Peran menentukan menu &amp; tindakan yang tersedia.
                    </p>
                </div>

                <button
                    type="button"
                    class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper"
                    @click="modalTambah = true"
                >
                    + Tambah Akun
                </button>
            </div>

            <PesanHasil />

            <form class="flex gap-2" @submit.prevent="cari">
                <input
                    v-model="cariLokal"
                    type="search"
                    placeholder="Cari nama atau email…"
                    class="brutal-sm w-64 bg-paper px-3 py-1.5 text-sm"
                >
                <button type="submit" class="brutal-sm brutal-hover bg-paper px-3 py-1.5 text-sm font-bold">Cari</button>
            </form>

            <div class="brutal overflow-x-auto bg-paper">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead class="border-b-2 border-ink bg-paper-alt text-left">
                        <tr>
                            <th class="px-4 py-3 font-display">Nama</th>
                            <th class="px-4 py-3 font-display">Peran</th>
                            <th class="px-4 py-3 font-display">Email</th>
                            <th class="px-4 py-3 font-display">Status</th>
                            <th class="px-4 py-3 text-right font-display">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="akun in daftar.data" :key="akun.id" class="border-b-2 border-ink/15">
                            <td class="px-4 py-3">
                                <p class="font-bold">{{ akun.nama }}</p>
                                <p class="text-xs text-muted">Dibuat {{ akun.dibuat_pada }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="peran in akun.peran"
                                        :key="peran"
                                        class="border-2 border-ink bg-accent-100 px-1.5 py-0.5 text-[11px] font-bold uppercase"
                                    >{{ labelPeran[peran] ?? peran }}</span>
                                    <span v-if="!akun.peran.length" class="text-xs text-muted">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ akun.email }}</p>
                                <p v-if="akun.adalah_saya" class="text-xs font-bold uppercase text-muted">Akunmu</p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="border-2 border-ink px-1.5 py-0.5 text-[11px] font-bold uppercase"
                                    :class="akun.terverifikasi ? 'bg-success/20' : 'bg-accent-100'"
                                >{{ akun.terverifikasi ? 'Terverifikasi' : 'Belum Verifikasi' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="brutal-sm brutal-hover bg-paper px-2 py-1 text-xs font-bold" @click="bukaUbah(akun)">
                                        Ubah
                                    </button>
                                    <button
                                        v-if="akun.id !== idSaya"
                                        type="button"
                                        class="brutal-sm brutal-hover bg-accent-100 px-2 py-1 text-xs font-bold"
                                        @click="hapus(akun)"
                                    >Hapus</button>
                                    <span v-else class="px-2 py-1 text-xs text-muted">—</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!daftar.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-muted">Tidak ada akun yang cocok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Penomoran :tautan="daftar.links" />
        </div>

        <!-- ===== Modal tambah ===== -->
        <div v-if="modalTambah" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-ink/60 p-4" @click.self="modalTambah = false">
            <form class="brutal w-full max-w-lg bg-paper p-5" @submit.prevent="simpanTambah">
                <h2 class="font-display text-lg">Tambah Akun Pengurus</h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="tambah-nama" class="block text-sm font-bold">Nama lengkap</label>
                        <input id="tambah-nama" v-model="formTambah.name" type="text" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="tambah-email" class="block text-sm font-bold">Email</label>
                        <input id="tambah-email" v-model="formTambah.email" type="email" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="tambah-sandi" class="block text-sm font-bold">Kata sandi</label>
                            <input id="tambah-sandi" v-model="formTambah.password" type="password" required autocomplete="new-password" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="tambah-sandi2" class="block text-sm font-bold">Ulangi kata sandi</label>
                            <input id="tambah-sandi2" v-model="formTambah.password_confirmation" type="password" required autocomplete="new-password" class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-muted">Minimal 12 karakter, memuat huruf dan angka.</p>

                    <fieldset>
                        <legend class="text-sm font-bold">Peran</legend>
                        <div class="mt-2 space-y-1">
                            <label v-for="peran in semuaPeran" :key="peran" class="flex items-center gap-2 text-sm">
                                <input v-model="formTambah.peran" type="checkbox" :value="peran" class="h-4 w-4 border-2 border-ink">
                                {{ labelPeran[peran] ?? peran }}
                            </label>
                        </div>
                    </fieldset>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="formTambah.verifikasi_sekarang" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        Tandai email sudah terverifikasi
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalTambah = false">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formTambah.processing">
                        Simpan
                    </button>
                </div>
            </form>
        </div>

        <!-- ===== Modal ubah ===== -->
        <div v-if="modalUbah && akunDisunting" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-ink/60 p-4" @click.self="modalUbah = false">
            <form class="brutal w-full max-w-lg bg-paper p-5" @submit.prevent="simpanUbah">
                <h2 class="font-display text-lg">Ubah Akun</h2>
                <p class="mt-1 text-xs text-muted">{{ akunDisunting.email }}</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label for="ubah-nama" class="block text-sm font-bold">Nama lengkap</label>
                        <input id="ubah-nama" v-model="formUbah.name" type="text" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="ubah-email" class="block text-sm font-bold">Email</label>
                        <input id="ubah-email" v-model="formUbah.email" type="email" required class="brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm">
                    </div>

                    <fieldset>
                        <legend class="text-sm font-bold">Peran</legend>
                        <div class="mt-2 space-y-1">
                            <label v-for="peran in semuaPeran" :key="peran" class="flex items-center gap-2 text-sm">
                                <input v-model="formUbah.peran" type="checkbox" :value="peran" class="h-4 w-4 border-2 border-ink">
                                {{ labelPeran[peran] ?? peran }}
                            </label>
                        </div>
                    </fieldset>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="formUbah.verifikasi_email" type="checkbox" class="h-4 w-4 border-2 border-ink">
                        Email sudah terverifikasi
                    </label>

                    <details class="border-2 border-ink/20 p-3">
                        <summary class="cursor-pointer text-sm font-bold">Ganti kata sandi (opsional)</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <input v-model="formUbah.password" type="password" placeholder="Kata sandi baru" autocomplete="new-password" class="brutal-sm bg-paper-alt px-3 py-2 text-sm">
                            <input v-model="formUbah.password_confirmation" type="password" placeholder="Ulangi" autocomplete="new-password" class="brutal-sm bg-paper-alt px-3 py-2 text-sm">
                        </div>
                        <p class="mt-2 text-xs text-muted">Biarkan kosong bila tidak ingin mengubah kata sandi.</p>
                    </details>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="brutal-sm bg-paper px-4 py-2 text-sm font-bold" @click="modalUbah = false">Batal</button>
                    <button type="submit" class="brutal-sm brutal-hover bg-primary-600 px-4 py-2 text-sm font-bold text-paper" :disabled="formUbah.processing">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </PanelLayout>
</template>
