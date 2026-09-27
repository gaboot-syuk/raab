#!/usr/bin/env bash
# ============================================================
# Perbaiki kepemilikan berkas tulis-menulis Laravel
#
# Kapan dipakai?
#   Bila muncul galat seperti:
#     "The stream or file .../storage/logs/laravel.log could not be opened
#      in append mode: Failed to open stream: Permission denied"
#
# Penyebab: berkas dibuat oleh proses yang berjalan sebagai root, sehingga
# pengguna container (UID sama dengan pengguna host) tidak dapat menulisnya.
#
# Cara pakai (dari akar proyek):
#   bash docker/dev/perbaiki-izin.sh
# ============================================================
set -euo pipefail

UID_TUJUAN="${APP_UID:-$(id -u)}"
GID_TUJUAN="${APP_GID:-$(id -g)}"

echo "→ Memperbaiki kepemilikan storage/ dan bootstrap/cache/ menjadi ${UID_TUJUAN}:${GID_TUJUAN}"

docker compose exec -T -u root app \
    chown -R "${UID_TUJUAN}:${GID_TUJUAN}" storage bootstrap/cache

echo "→ Membersihkan cache aplikasi"
docker compose exec -T app php artisan cache:clear

echo "Selesai. Silakan muat ulang halaman yang bermasalah."
