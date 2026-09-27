<script setup lang="ts">
/*
 * Penyunting artikel — dua bahasa dalam satu formulir.
 * Isi ditulis dalam HTML sederhana (boleh <p>, <h2>, <blockquote>, <strong>),
 * dengan tombol bantu untuk menyisipkan tag yang paling sering dipakai.
 */
import PanelLayout from '@/Layouts/PanelLayout.vue';
import PesanHasil from '@/Components/Panel/PesanHasil.vue';
import EditorKaya from '@/Components/Panel/EditorKaya.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface DetailArtikel {
    id: number;
    tipe: string;
    label_tipe: string;
    status: string;
    label_status: string;
    kategori_id: number | null;
    cover_media_id: number | null;
    cover_url: string | null;
    judul: Record<string, string | null>;
    slug: Record<string, string | null>;
    ringkasan: Record<string, string | null>;
    konten: Record<string, string | null>;
    seo_judul: Record<string, string | null>;
    seo_deskripsi: Record<string, string | null>;
    tag: string;
    unggulan: boolean;
    dijadwalkan_pada: string | null;
    catatan_review: string | null;
    nomor_dokumen: string | null;
    tanggal_agenda: string | null;
    agenda: string | null;
    keputusan: string | null;
    penandatangan: string | null;
    jabatan_penandatangan: string | null;
    tautan_publik: string | null;
    revisi: { id: number; judul: string | null; status: string | null; catatan: string | null; oleh: string; waktu: string }[];
}

const props = defineProps<{
    artikel: DetailArtikel | null;
    pilihanTipe: Record<string, string>;
    daftarKategori: { id: number; nama: string }[];
    pilihanGambar: { id: number; nama: string; url: string }[];
    tagPopuler: { id: number; nama: string }[];
    penerjemah: { tersedia: boolean; alasan: string | null };
}>();

const kolom = 'brutal-sm mt-1 w-full bg-paper-alt px-3 py-2 text-sm';
const bahasa = ref<'id' | 'en'>('id');

const form = useForm({
    tipe: props.artikel?.tipe ?? 'berita',
    kategori_id: props.artikel?.kategori_id ?? null,
    cover_media_id: props.artikel?.cover_media_id ?? null,
    judul: { id: props.artikel?.judul?.id ?? '', en: props.artikel?.judul?.en ?? '' } as Record<string, string>,
    slug: { id: props.artikel?.slug?.id ?? '', en: props.artikel?.slug?.en ?? '' } as Record<string, string>,
    ringkasan: { id: props.artikel?.ringkasan?.id ?? '', en: props.artikel?.ringkasan?.en ?? '' } as Record<string, string>,
    konten: { id: props.artikel?.konten?.id ?? '', en: props.artikel?.konten?.en ?? '' } as Record<string, string>,
    seo_judul: { id: props.artikel?.seo_judul?.id ?? '', en: props.artikel?.seo_judul?.en ?? '' } as Record<string, string>,
    seo_deskripsi: { id: props.artikel?.seo_deskripsi?.id ?? '', en: props.artikel?.seo_deskripsi?.en ?? '' } as Record<string, string>,
    tag: props.artikel?.tag ?? '',
    unggulan: props.artikel?.unggulan ?? false,
    dijadwalkan_pada: props.artikel?.dijadwalkan_pada ?? '',
    nomor_dokumen: props.artikel?.nomor_dokumen ?? '',
    tanggal_agenda: props.artikel?.tanggal_agenda ?? '',
    agenda: props.artikel?.agenda ?? '',
    keputusan: props.artikel?.keputusan ?? '',
    penandatangan: props.artikel?.penandatangan ?? '',
    jabatan_penandatangan: props.artikel?.jabatan_penandatangan ?? '',
});

const beritaAcara = computed(() => form.tipe === 'berita_acara');
const adaRevisi = computed(() => (props.artikel?.revisi?.length ?? 0) > 0);

function simpan() {
    if (props.artikel) {
        form.put(`/panel/artikel/${props.artikel.id}`, { preserveScroll: true });
    } else {
        form.post('/panel/artikel', { preserveScroll: true });
    }
}

function aksi(jalur: string) {
    if (!props.artikel) {
        return;
    }

    router.post(`/panel/artikel/${props.artikel.id}/${jalur}`, {}, { preserveScroll: true });
}
</script>

<template>
    <PanelLayout>
        <Head :title="artikel ? `Sunting — ${artikel.judul.id}` : 'Artikel Baru'" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link href="/panel/artikel" class="text-xs font-bold uppercase underline">← Publikasi</Link>
                    <h1 class="mt-2 font-display text-2xl">{{ artikel ? 'Sunting Artikel' : 'Artikel Baru' }}</h1>
                    <p v-if="artikel" class="mt-1 text-sm text-muted">
                        Status: <strong>{{ artikel.label_status }}</strong> · {{ artikel.label_tipe }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a v-if="artikel?.tautan_publik" :href="artikel.tautan_publik" target="_blank" rel="noopener noreferrer" class="brutal-sm brutal-hover bg-paper px-3 py-2 text-sm font-bold">
                        Lihat di Situs
                    </a>

                    <a
                        v-if="artikel && artikel.tipe === 'berita_acara'"
                        :href="`/panel/artikel/${artikel.id}/pdf`"
                        class="brutal-sm brutal-hover bg-paper px-3 py-2 text-sm font-bold"
                    >Unduh PDF</a>
                    <button
                        v-if="artikel && (artikel.status === 'draf' || artikel.status === 'perlu_revisi')"
                        type="button"
                        class="brutal-sm brutal-hover bg-accent-400 px-3 py-2 text-sm font-bold text-primary-800"
                        @click="aksi('kirim')"
                    >Kirim untuk Review</button>
                    <button
                        v-if="artikel && (artikel.status === 'menunggu_review' || artikel.status === 'draf')"
                        type="button"
                        class="brutal-sm brutal-hover bg-primary-600 px-3 py-2 text-sm font-bold text-paper"
                        @click="aksi('terbitkan')"
                    >Terbitkan</button>

                    <button
                        v-if="artikel"
                        type="button"
                        class="brutal-sm brutal-hover bg-paper px-3 py-2 text-sm font-bold"
                        :title="penerjemah.tersedia ? 'Terjemahkan ulang isi ke bahasa Inggris' : (penerjemah.alasan ?? '')"
                        @click="aksi('terjemahkan')"
                    >Terjemahkan Ulang</button>
                </div>
            </div>

            <PesanHasil />

            <div v-if="artikel?.catatan_review" class="brutal-sm border-ink bg-accent-100 px-4 py-3 text-sm">
                <p class="font-bold">Catatan dari pengelola konten</p>
                <p class="mt-1">{{ artikel.catatan_review }}</p>
            </div>

            <p v-if="artikel && !penerjemah.tersedia" class="brutal-sm border-ink bg-paper-alt px-4 py-3 text-xs text-muted">
                <strong>Terjemahan otomatis belum aktif.</strong> {{ penerjemah.alasan }}
                Sementara ini, isi versi Inggris dapat diketik manual pada tab <strong>Inggris</strong> di bawah.
            </p>

            <form class="space-y-6" @submit.prevent="simpan">
                <!-- Jenis & kategori -->
                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Jenis &amp; Kategori</legend>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="tipe" class="block text-sm font-bold">Tipe *</label>
                            <select id="tipe" v-model="form.tipe" required :class="kolom">
                                <option v-for="(label, nilai) in pilihanTipe" :key="nilai" :value="nilai">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="kategori_id" class="block text-sm font-bold">Kategori</label>
                            <select id="kategori_id" v-model="form.kategori_id" :class="kolom">
                                <option :value="null">— Tanpa kategori —</option>
                                <option v-for="k in daftarKategori" :key="k.id" :value="k.id">{{ k.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="cover_media_id" class="block text-sm font-bold">Gambar sampul</label>
                            <select id="cover_media_id" v-model="form.cover_media_id" :class="kolom">
                                <option :value="null">— Tanpa gambar —</option>
                                <option v-for="g in pilihanGambar" :key="g.id" :value="g.id">{{ g.nama }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="tag" class="block text-sm font-bold">Tag</label>
                            <input id="tag" v-model="form.tag" type="text" placeholder="pisahkan dengan koma" :class="kolom">
                            <p v-if="tagPopuler.length" class="mt-1 text-xs text-muted">
                                Sering dipakai:
                                <button
                                    v-for="t in tagPopuler.slice(0, 8)"
                                    :key="t.id"
                                    type="button"
                                    class="ml-1 underline"
                                    @click="form.tag = [form.tag, t.nama].filter(Boolean).join(', ') + ', '"
                                >{{ t.nama }}</button>
                            </p>
                        </div>
                        <div class="flex flex-col justify-end gap-2">
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input v-model="form.unggulan" type="checkbox" class="h-4 w-4 border-2 border-ink">
                                Tandai sebagai unggulan (sorotan beranda &amp; publikasi)
                            </label>
                            <div>
                                <label for="jadwal" class="block text-sm font-bold">Jadwalkan terbit</label>
                                <input id="jadwal" v-model="form.dijadwalkan_pada" type="datetime-local" :class="kolom">
                                <p class="mt-1 text-xs text-muted">Kosongkan untuk terbit seketika.</p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- Isi dwibahasa -->
                <fieldset class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Isi Artikel</legend>

                    <div class="flex gap-2">
                        <button
                            v-for="b in (['id', 'en'] as const)"
                            :key="b"
                            type="button"
                            class="brutal-sm px-3 py-1.5 text-sm font-bold"
                            :class="bahasa === b ? 'bg-accent-400 text-primary-800' : 'bg-paper-alt'"
                            @click="bahasa = b"
                        >{{ b === 'id' ? 'Indonesia' : 'Inggris' }}</button>
                        <span class="ml-auto self-center text-xs text-muted">
                            Bahasa Inggris boleh dikosongkan — situs akan menampilkan versi Indonesia.
                        </span>
                    </div>

                    <div>
                        <label :for="`judul-${bahasa}`" class="block text-sm font-bold">Judul {{ bahasa === 'id' ? '*' : '' }}</label>
                        <input :id="`judul-${bahasa}`" v-model="form.judul[bahasa]" type="text" maxlength="190" :class="kolom">
                    </div>

                    <div>
                        <label :for="`slug-${bahasa}`" class="block text-sm font-bold">Slug URL</label>
                        <input :id="`slug-${bahasa}`" v-model="form.slug[bahasa]" type="text" maxlength="190" placeholder="otomatis dari judul" :class="kolom">
                    </div>

                    <div>
                        <label :for="`ringkasan-${bahasa}`" class="block text-sm font-bold">Ringkasan</label>
                        <textarea :id="`ringkasan-${bahasa}`" v-model="form.ringkasan[bahasa]" rows="2" maxlength="500" :class="kolom" />
                    </div>

                    <div>
                        <label class="block text-sm font-bold">
                            Isi {{ bahasa === 'id' ? '*' : '' }}
                        </label>

                        <EditorKaya v-model="form.konten[bahasa]" :placeholder="bahasa === 'id' ? 'Tulis isi artikel…' : 'Write the English version…'" />

                        <p class="mt-1 text-xs text-muted">
                            Gunakan tombol <strong>🖼</strong> untuk menyisipkan gambar — salin dulu tautannya
                            dari <a href="/panel/media" class="underline">Pustaka Media</a>.
                            Untuk sastra, gunakan tombol kutipan (❝) pada bait yang perlu ditonjolkan.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label :for="`seo-judul-${bahasa}`" class="block text-sm font-bold">Judul SEO</label>
                            <input :id="`seo-judul-${bahasa}`" v-model="form.seo_judul[bahasa]" type="text" maxlength="190" :class="kolom">
                        </div>
                        <div>
                            <label :for="`seo-deskripsi-${bahasa}`" class="block text-sm font-bold">Deskripsi SEO</label>
                            <input :id="`seo-deskripsi-${bahasa}`" v-model="form.seo_deskripsi[bahasa]" type="text" maxlength="300" :class="kolom">
                        </div>
                    </div>
                </fieldset>

                <!-- Berita acara -->
                <fieldset v-if="beritaAcara" class="brutal space-y-4 bg-paper p-5">
                    <legend class="font-display text-lg">Data Berita Acara</legend>
                    <p class="text-sm text-muted">
                        Berita acara diterbitkan langsung oleh Sekretaris tanpa melewati review.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nomor_dokumen" class="block text-sm font-bold">Nomor dokumen *</label>
                            <input id="nomor_dokumen" v-model="form.nomor_dokumen" type="text" placeholder="Mis. 012/BA/RAAB/IX/2026" :class="kolom">
                        </div>
                        <div>
                            <label for="tanggal_agenda" class="block text-sm font-bold">Tanggal agenda *</label>
                            <input id="tanggal_agenda" v-model="form.tanggal_agenda" type="date" :class="kolom">
                        </div>
                        <div>
                            <label for="penandatangan" class="block text-sm font-bold">Penandatangan *</label>
                            <input id="penandatangan" v-model="form.penandatangan" type="text" :class="kolom">
                        </div>
                        <div>
                            <label for="jabatan_penandatangan" class="block text-sm font-bold">Jabatan penandatangan *</label>
                            <input id="jabatan_penandatangan" v-model="form.jabatan_penandatangan" type="text" placeholder="Sekretaris Rayon" :class="kolom">
                        </div>
                    </div>

                    <div>
                        <label for="agenda" class="block text-sm font-bold">Agenda rapat *</label>
                        <textarea id="agenda" v-model="form.agenda" rows="3" :class="kolom" />
                    </div>

                    <div>
                        <label for="keputusan" class="block text-sm font-bold">Keputusan *</label>
                        <textarea id="keputusan" v-model="form.keputusan" rows="6" :class="kolom" />
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="brutal brutal-hover bg-accent-400 px-6 py-3 font-bold text-primary-800" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan Artikel' }}
                    </button>
                    <Link href="/panel/artikel" class="brutal bg-paper px-6 py-3 font-bold">Kembali</Link>
                </div>
            </form>

            <!-- Riwayat revisi -->
            <section v-if="adaRevisi && artikel" class="brutal bg-paper p-5">
                <h2 class="font-display text-lg">Riwayat Revisi</h2>
                <p class="mt-1 text-xs text-muted">Menyimpan {{ artikel.revisi.length }} versi terakhir.</p>
                <ul class="mt-3 divide-y-2 divide-ink/10">
                    <li v-for="r in artikel.revisi" :key="r.id" class="py-2 text-sm">
                        <p class="font-bold">{{ r.judul || '(tanpa judul)' }}</p>
                        <p class="text-xs text-muted">
                            {{ r.catatan || 'Disunting' }} · {{ r.status || '—' }} · oleh {{ r.oleh }} · {{ r.waktu }}
                        </p>
                    </li>
                </ul>
            </section>
        </div>
    </PanelLayout>
</template>
