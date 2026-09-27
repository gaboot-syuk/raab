<script setup lang="ts">
/*
 * Profil anggota & pengaturan privasi.
 *
 * Data yang diisi di sini dipakai pada direktori publik, tetapi HANYA bagian
 * yang dicentang pada bagian "Privasi" yang akan tampil di sana.
 */
import AnggotaLayout from '@/Layouts/AnggotaLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    anggota: {
        nama_lengkap: string;
        nama_panggilan: string | null;
        jenis_kelamin: string | null;
        tempat_lahir: string | null;
        tanggal_lahir: string | null;
        nim: string | null;
        fakultas: string | null;
        program_studi: string | null;
        angkatan: number | null;
        alamat: string | null;
        telepon: string | null;
        email_kontak: string | null;
        unit_id: number | null;
        keahlian: string;
        sosmed: Record<string, string>;
        privasi: Record<string, boolean>;
        profil_publik: boolean;
        jalur: string;
        label_status: string;
        nama_akun: string;
        email_akun: string;
        alumni: {
            tahun_lulus: number | null;
            instansi: string | null;
            jabatan: string | null;
            bidang: string | null;
            kota_domisili: string | null;
            latitude: number | null;
            longitude: number | null;
            bersedia_mentor: boolean;
            topik_mentor: string | null;
        } | null;
    } | null;
    daftarUnit: { id: number; nama: string; jenis: string }[];
    kolomPrivasi: Record<string, string>;
}>();

const kolom = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm';

const form = useForm({
    nama_lengkap: props.anggota?.nama_lengkap ?? '',
    nama_panggilan: props.anggota?.nama_panggilan ?? '',
    jenis_kelamin: props.anggota?.jenis_kelamin ?? 'laki_laki',
    tempat_lahir: props.anggota?.tempat_lahir ?? '',
    tanggal_lahir: props.anggota?.tanggal_lahir ?? '',
    nim: props.anggota?.nim ?? '',
    fakultas: props.anggota?.fakultas ?? '',
    program_studi: props.anggota?.program_studi ?? '',
    angkatan: props.anggota?.angkatan ?? null,
    alamat: props.anggota?.alamat ?? '',
    telepon: props.anggota?.telepon ?? '',
    email_kontak: props.anggota?.email_kontak ?? '',
    unit_id: props.anggota?.unit_id ?? null,
    keahlian: props.anggota?.keahlian ?? '',
    sosmed: {
        instagram: props.anggota?.sosmed?.instagram ?? '',
        linkedin: props.anggota?.sosmed?.linkedin ?? '',
        tiktok: props.anggota?.sosmed?.tiktok ?? '',
    } as Record<string, string>,
    privasi: { ...(props.anggota?.privasi ?? {}) } as Record<string, boolean>,
    profil_publik: props.anggota?.profil_publik ?? false,
    tahun_lulus: props.anggota?.alumni?.tahun_lulus ?? null,
    instansi: props.anggota?.alumni?.instansi ?? '',
    jabatan: props.anggota?.alumni?.jabatan ?? '',
    bidang: props.anggota?.alumni?.bidang ?? '',
    kota_domisili: props.anggota?.alumni?.kota_domisili ?? '',
    latitude: props.anggota?.alumni?.latitude ?? null,
    longitude: props.anggota?.alumni?.longitude ?? null,
    bersedia_mentor: props.anggota?.alumni?.bersedia_mentor ?? false,
    topik_mentor: props.anggota?.alumni?.topik_mentor ?? '',
});

function simpan() {
    form.put('/profil', { preserveScroll: true });
}
</script>

<template>
    <AnggotaLayout>
        <Head title="Profil Saya" />

        <div class="space-y-6">
            <div>
                <h1 class="font-display text-2xl sm:text-3xl">Profil Saya</h1>
                <p class="mt-1 text-sm text-muted">
                    Status keanggotaan: <strong>{{ anggota?.label_status ?? '—' }}</strong> ·
                    Akun: {{ anggota?.email_akun ?? '—' }}
                </p>
            </div>

            <PesanHasil />

            <p v-if="!anggota" class="brutal bg-paper-alt p-6 text-sm text-muted">
                Profil belum tersedia — pengajuanmu masih menunggu verifikasi Sekretaris.
                Kamu tetap dapat menyunting nama dan alamat email akun melalui panel pengurus.
            </p>

            <form v-else class="space-y-6" @submit.prevent="simpan">
                <!-- Data pribadi -->
                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Data Pribadi</legend>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="nama_lengkap" class="block text-sm font-bold">Nama lengkap *</label>
                            <input id="nama_lengkap" v-model="form.nama_lengkap" type="text" required maxlength="160" :class="kolom">
                        </div>
                        <div>
                            <label for="nama_panggilan" class="block text-sm font-bold">Nama panggilan</label>
                            <input id="nama_panggilan" v-model="form.nama_panggilan" type="text" maxlength="60" :class="kolom">
                        </div>
                        <div>
                            <label for="jenis_kelamin" class="block text-sm font-bold">Jenis kelamin *</label>
                            <select id="jenis_kelamin" v-model="form.jenis_kelamin" required :class="kolom">
                                <option value="laki_laki">Laki-laki</option>
                                <option value="perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label for="tempat_lahir" class="block text-sm font-bold">Tempat lahir</label>
                            <input id="tempat_lahir" v-model="form.tempat_lahir" type="text" maxlength="120" :class="kolom">
                        </div>
                        <div>
                            <label for="tanggal_lahir" class="block text-sm font-bold">Tanggal lahir</label>
                            <input id="tanggal_lahir" v-model="form.tanggal_lahir" type="date" :class="kolom">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="alamat" class="block text-sm font-bold">Alamat tinggal</label>
                            <input id="alamat" v-model="form.alamat" type="text" maxlength="500" :class="kolom">
                        </div>
                        <div>
                            <label for="telepon" class="block text-sm font-bold">Nomor telepon / WhatsApp</label>
                            <input id="telepon" v-model="form.telepon" type="text" maxlength="40" :class="kolom">
                        </div>
                        <div>
                            <label for="email_kontak" class="block text-sm font-bold">Email kontak (selain email akun)</label>
                            <input id="email_kontak" v-model="form.email_kontak" type="email" maxlength="190" :class="kolom">
                        </div>
                    </div>
                </fieldset>

                <!-- Kampus & minat -->
                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Kampus &amp; Minat</legend>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nim" class="block text-sm font-bold">NIM</label>
                            <input id="nim" v-model="form.nim" type="text" maxlength="40" :class="kolom">
                        </div>
                        <div>
                            <label for="angkatan" class="block text-sm font-bold">Tahun angkatan</label>
                            <input id="angkatan" v-model.number="form.angkatan" type="number" min="2000" :class="kolom">
                        </div>
                        <div>
                            <label for="fakultas" class="block text-sm font-bold">Fakultas</label>
                            <input id="fakultas" v-model="form.fakultas" type="text" maxlength="160" :class="kolom">
                        </div>
                        <div>
                            <label for="program_studi" class="block text-sm font-bold">Program studi</label>
                            <input id="program_studi" v-model="form.program_studi" type="text" maxlength="160" :class="kolom">
                        </div>
                        <div>
                            <label for="unit_id" class="block text-sm font-bold">Biro / LSO</label>
                            <select id="unit_id" v-model="form.unit_id" :class="kolom">
                                <option :value="null">— Belum memilih —</option>
                                <option v-for="u in daftarUnit" :key="u.id" :value="u.id">{{ u.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="keahlian" class="block text-sm font-bold">Keahlian</label>
                            <input id="keahlian" v-model="form.keahlian" type="text" maxlength="500" placeholder="Pisahkan dengan koma" :class="kolom">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div v-for="platform in (['instagram', 'linkedin', 'tiktok'] as const)" :key="platform">
                            <label :for="`sosmed-${platform}`" class="block text-sm font-bold capitalize">{{ platform }}</label>
                            <input :id="`sosmed-${platform}`" v-model="form.sosmed[platform]" type="text" maxlength="190" :class="kolom">
                        </div>
                    </div>
                </fieldset>

                <!-- Data alumni -->
                <fieldset v-if="anggota.jalur === 'alumni'" class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Data Alumni</legend>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="tahun_lulus" class="block text-sm font-bold">Tahun lulus</label>
                            <input id="tahun_lulus" v-model.number="form.tahun_lulus" type="number" min="2000" :class="kolom">
                        </div>
                        <div>
                            <label for="instansi" class="block text-sm font-bold">Instansi / tempat kerja</label>
                            <input id="instansi" v-model="form.instansi" type="text" maxlength="190" :class="kolom">
                        </div>
                        <div>
                            <label for="jabatan" class="block text-sm font-bold">Jabatan</label>
                            <input id="jabatan" v-model="form.jabatan" type="text" maxlength="160" :class="kolom">
                        </div>
                        <div>
                            <label for="bidang" class="block text-sm font-bold">Bidang</label>
                            <input id="bidang" v-model="form.bidang" type="text" maxlength="160" :class="kolom">
                        </div>
                        <div>
                            <label for="kota_domisili" class="block text-sm font-bold">Kota domisili</label>
                            <input id="kota_domisili" v-model="form.kota_domisili" type="text" maxlength="120" :class="kolom">
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input v-model="form.bersedia_mentor" type="checkbox" class="h-4 w-4 border-2 border-ink">
                                Siap menjadi mentor / pemateri
                            </label>
                        </div>
                        <div>
                            <label for="latitude" class="block text-sm font-bold">Lintang (peta sebaran)</label>
                            <input id="latitude" v-model.number="form.latitude" type="number" step="0.0000001" min="-90" max="90" placeholder="-7.5561" :class="kolom">
                        </div>
                        <div>
                            <label for="longitude" class="block text-sm font-bold">Bujur (peta sebaran)</label>
                            <input id="longitude" v-model.number="form.longitude" type="number" step="0.0000001" min="-180" max="180" placeholder="110.8316" :class="kolom">
                        </div>
                        <p class="text-xs text-muted sm:col-span-2">
                            Isi lintang & bujur bila bersedia muncul di peta sebaran alumni. Keduanya opsional.
                        </p>
                        <div class="sm:col-span-2">
                            <label for="topik_mentor" class="block text-sm font-bold">Topik yang dikuasai (bila bersedia menjadi mentor)</label>
                            <textarea id="topik_mentor" v-model="form.topik_mentor" rows="2" :class="kolom" />
                        </div>
                    </div>
                </fieldset>

                <!-- Privasi -->
                <fieldset class="brutal space-y-3 bg-paper p-5">
                    <legend class="font-display text-lg">Privasi Direktori</legend>
                    <p class="text-sm text-muted">
                        Centang data yang boleh tampil di halaman publik Direktori Anggota / Alumni.
                        Nomor telepon, email, NIM, dan alamat selalu tertutup kecuali kamu centang di sini.
                    </p>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="(label, kunci) in kolomPrivasi" :key="kunci" class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                class="h-4 w-4 border-2 border-ink"
                                :checked="form.privasi[kunci] ?? false"
                                @change="form.privasi[kunci] = ($event.target as HTMLInputElement).checked"
                            >
                            {{ label }}
                        </label>
                    </div>

                    <label class="mt-3 flex items-start gap-2 border-t-2 border-ink/10 pt-3 text-sm font-bold">
                        <input v-model="form.profil_publik" type="checkbox" class="mt-0.5 h-4 w-4 border-2 border-ink">
                        <span>
                            Buka halaman profil publik saya
                            <span class="block text-xs font-normal text-muted">
                                Membuka alamat <code>/prestasi/kader/</code> berisi riwayat kepengurusan dan karya tulis.
                                Tanpa ini, halaman tersebut tidak dapat diakses siapa pun.
                            </span>
                        </span>
                    </label>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Profil' }}
                    </button>
                    <a href="/dasbor" class="brutal bg-paper px-6 py-3 font-bold">Batal</a>
                </div>
            </form>
        </div>
    </AnggotaLayout>
</template>
