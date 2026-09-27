<script setup lang="ts">
/*
 * Editor teks kaya (TipTap) untuk menulis artikel & karya anggota.
 *
 * Nilai yang dikirim ke server adalah HTML — sama seperti yang disimpan pada
 * kolom `konten`, sehingga isi lama (HTML sederhana) tetap dapat dibuka.
 *
 * Gambar disisipkan lewat URL dari Pustaka Media (tombol "Salin tautan" di
 * sana), bukan diunggah langsung — supaya seluruh berkas tetap terkelola di
 * satu tempat dan tidak ada berkas yatim yang tidak terpakai.
 */
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
    }>(),
    { placeholder: 'Tulis di sini…' },
);

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const editor = useEditor({
    content: props.modelValue ?? '',
    extensions: [
        /*
         * `link` DIKONFIGURASI DI DALAM StarterKit, bukan didaftarkan lagi
         * terpisah.
         *
         * StarterKit v3 sudah memuat ekstensi Link. Menambahkannya sekali lagi
         * membuat Link terdaftar dua kali, dan TipTap memperingatkan:
         * "Duplicate extension names found: ['link']". Dua ekstensi dengan nama
         * sama berebut menangani hal yang sama — tombol tautan bisa berperilaku
         * tidak terduga, dan yang paling membingungkan: ia bekerja pada satu
         * penyunting lalu tidak pada penyunting lain.
         */
        StarterKit.configure({
            heading: { levels: [2, 3, 4] },
            link: {
                openOnClick: false,
                autolink: true,
                HTMLAttributes: { rel: 'noopener noreferrer' },
            },
        }),
        Image,
    ],
    editorProps: {
        attributes: {
            class: 'editor-kaya',
            'aria-label': props.placeholder,
        },
    },
    onUpdate: ({ editor: ed }) => emit('update:modelValue', ed.getHTML()),
});

/*
 * Sinkronkan bila nilai berubah dari luar (mis. tombol "Terjemahkan ulang"
 * mengisi versi Inggris). Bandingkan dulu dengan isi editor agar tidak
 * menimpa ketikan pengguna saat halaman memuat ulang props.
 */
watch(
    () => props.modelValue,
    (baru) => {
        const ed = editor.value;

        if (!ed) {
            return;
        }

        const sekarang = ed.getHTML();

        if (baru === sekarang) {
            return;
        }

        ed.commands.setContent(baru ?? '');
    },
);

onBeforeUnmount(() => editor.value?.destroy());

function tautan() {
    const ed = editor.value;

    if (!ed) {
        return;
    }

    sisip.value = sisip.value === 'tautan' ? null : 'tautan';
    alamat.value = (ed.getAttributes('link').href as string) ?? '';
}

function gambar() {
    if (!editor.value) {
        return;
    }

    sisip.value = sisip.value === 'gambar' ? null : 'gambar';
    alamat.value = '';
}

/*
 * Alamat tautan/gambar diisi lewat kotak kecil di bawah toolbar, bukan
 * window.prompt(). Prompt diblokir di konteks ber-sandbox sehingga tombol
 * toolbar-nya diam saja, dan isinya tidak bisa diperiksa sebelum disisipkan.
 */
type Sisip = 'tautan' | 'gambar';

const sisip = ref<Sisip | null>(null);
const alamat = ref('');

function jalankanSisip() {
    const ed = editor.value;

    if (!ed || !sisip.value) {
        return;
    }

    const nilai = alamat.value.trim();

    if (sisip.value === 'tautan') {
        if (nilai === '') {
            ed.chain().focus().extendMarkRange('link').unsetLink().run();
        } else {
            ed.chain().focus().extendMarkRange('link').setLink({ href: nilai }).run();
        }
    } else if (nilai !== '') {
        ed.chain().focus().setImage({ src: nilai }).run();
    }

    sisip.value = null;
    alamat.value = '';
}

/**
 * Daftar tombol toolbar. Disederhanakan jadi data agar markup tidak berulang.
 */
const tombol = [
    { label: 'B', judul: 'Tebal', kelas: 'font-bold', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleBold().run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('bold') },
    { label: 'I', judul: 'Miring', kelas: 'italic', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleItalic().run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('italic') },
    { label: 'H2', judul: 'Judul tingkat 2', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleHeading({ level: 2 }).run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('heading', { level: 2 }) },
    { label: 'H3', judul: 'Judul tingkat 3', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleHeading({ level: 3 }).run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('heading', { level: 3 }) },
    { label: '❝', judul: 'Kutipan', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleBlockquote().run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('blockquote') },
    { label: '•', judul: 'Daftar butir', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleBulletList().run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('bulletList') },
    { label: '1.', judul: 'Daftar bernomor', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().toggleOrderedList().run(), aktif: (e: NonNullable<typeof editor.value>) => e.isActive('orderedList') },
    { label: '—', judul: 'Garis pemisah', kelas: '', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().setHorizontalRule().run(), aktif: () => false },
];

const riwayat = [
    { label: '↶', judul: 'Batalkan', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().undo().run() },
    { label: '↷', judul: 'Ulangi', aksi: (e: NonNullable<typeof editor.value>) => e.chain().focus().redo().run() },
];
</script>

<template>
    <div class="brutal-sm mt-1 border-ink bg-paper-alt">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b-2 border-ink p-1.5">
            <button
                v-for="t in tombol"
                :key="t.judul"
                type="button"
                :title="t.judul"
                class="border-2 border-ink px-2 py-0.5 text-xs font-bold"
                :class="[t.kelas, t.aktif(editor) ? 'bg-accent-400 text-primary-800' : 'bg-paper']"
                @click="t.aksi(editor)"
            >{{ t.label }}</button>

            <button type="button" title="Sisipkan tautan" class="border-2 border-ink bg-paper px-2 py-0.5 text-xs font-bold" @click="tautan">🔗</button>
            <button type="button" title="Sisipkan gambar" class="border-2 border-ink bg-paper px-2 py-0.5 text-xs font-bold" @click="gambar">🖼</button>

            <span class="mx-1 h-5 w-0.5 bg-ink/25"></span>

            <button
                v-for="t in riwayat"
                :key="t.judul"
                type="button"
                :title="t.judul"
                class="border-2 border-ink bg-paper px-2 py-0.5 text-xs font-bold"
                @click="t.aksi(editor)"
            >{{ t.label }}</button>
        </div>

        <!-- Isian alamat tautan/gambar -->
        <form
            v-if="sisip"
            class="flex flex-wrap items-center gap-2 border-b-2 border-ink bg-accent-100 p-1.5"
            @submit.prevent="jalankanSisip"
        >
            <label class="text-xs font-bold uppercase">
                {{ sisip === 'tautan' ? 'Alamat tautan' : 'Alamat gambar' }}
            </label>
            <input
                v-model="alamat"
                type="url"
                :placeholder="sisip === 'tautan' ? 'https://contoh.id/artikel' : 'https://contoh.id/gambar.jpg'"
                class="min-w-40 flex-1 border-2 border-ink bg-paper px-2 py-1 text-xs"
            >
            <button type="submit" class="border-2 border-ink bg-accent-400 px-2 py-1 text-xs font-bold text-primary-800">
                Sisipkan
            </button>
            <button type="button" class="border-2 border-ink bg-paper px-2 py-1 text-xs font-bold" @click="sisip = null">
                Batal
            </button>
            <span v-if="sisip === 'tautan'" class="text-xs text-muted">Kosongkan untuk melepas tautan.</span>
        </form>

        <!-- Area tulis -->
        <EditorContent :editor="editor" />
    </div>
</template>
