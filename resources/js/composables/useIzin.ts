/*
 * Membaca izin pengguna yang sedang masuk.
 *
 * KENAPA ADA
 *
 * Panel menampilkan tombol berdasarkan izin. Kalau tombol yang pasti ditolak
 * server tetap ditampilkan, pengurus menekannya dan hanya melihat modal galat
 * tanpa penjelasan — dan yang dilaporkan sebagai bug adalah "tombolnya tidak
 * berfungsi", padahal penolakannya memang benar.
 *
 * Ini BUKAN pengaman. Penjaganya tetap middleware `permission:` di
 * `routes/web.php`, yang berjalan sebelum controller. Menyembunyikan tombol
 * hanya mencegah orang menempuh jalan buntu; ia tidak menggantikan penjagaan.
 *
 * Izinnya sudah dikirim sekali oleh `HandleInertiaRequests` pada
 * `auth.permissions`, jadi membaca di sini tidak menambah permintaan apa pun.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useIzin() {
    const daftar = computed<string[]>(() => {
        const auth = usePage().props.auth as { permissions?: string[] } | undefined;

        return auth?.permissions ?? [];
    });

    /** Apakah pengguna yang sedang masuk memiliki izin ini. */
    function boleh(izin: string): boolean {
        return daftar.value.includes(izin);
    }

    return { daftar, boleh };
}
