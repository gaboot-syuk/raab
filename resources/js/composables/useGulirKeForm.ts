import { nextTick, ref, type Ref } from 'vue';

/**
 * Menggulir layar ke formulir sunting.
 *
 * Sebagian halaman panel menaruh SATU formulir di bagian paling atas,
 * sementara tombol "Sunting" berada di daftar yang panjang di bawahnya. Pada
 * halaman seperti itu, menekan "Sunting" tampak tidak melakukan apa pun:
 * formulirnya memang terisi, tetapi letaknya bisa lebih dari seribu piksel di
 * luar layar. Yang berubah hanya bagian halaman yang tidak sedang dilihat.
 *
 * Gulir ini yang membuat perubahan itu terlihat.
 *
 * Cara pakai:
 *
 *     const { wadah, gulirKeForm } = useGulirKeForm();
 *
 * Lalu pasang `ref="wadah"` pada elemen `<form>` yang bersangkutan, dan
 * panggil `gulirKeForm()` di baris terakhir fungsi yang mengisi formulir itu.
 *
 * Satu halaman boleh memakai lebih dari satu wadah, misalnya bila ada dua
 * formulir berbeda di halaman yang sama.
 */
export function useGulirKeForm(): {
    wadah: Ref<HTMLElement | null>;
    gulirKeForm: () => void;
} {
    const wadah = ref<HTMLElement | null>(null);

    function gulirKeForm(): void {
        // Menunggu satu putaran render: judul formulir dan tombol "Batal"
        // baru muncul setelah state sunting terisi, sehingga tinggi formulir
        // bisa berubah. Mengukur sebelum itu menghasilkan posisi yang meleset.
        void nextTick(() => {
            const elemen = wadah.value;

            if (!elemen) {
                return;
            }

            const jarakAtas = 16;
            const tujuan = elemen.getBoundingClientRect().top + window.scrollY - jarakAtas;

            const perilaku: ScrollBehavior = window.matchMedia(
                '(prefers-reduced-motion: reduce)',
            ).matches
                ? 'auto'
                : 'smooth';

            window.scrollTo({ top: Math.max(tujuan, 0), behavior: perilaku });
        });
    }

    return { wadah, gulirKeForm };
}
