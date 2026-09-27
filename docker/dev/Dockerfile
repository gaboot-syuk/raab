# ============================================================
# Lingkungan Pengembangan — PMII Rayon Ali Ahmad Baktsir
# PHP 8.4 CLI + ekstensi wajib Laravel 13
# ============================================================
FROM php:8.4-cli

ARG UID=1000
ARG GID=1000

# --- Ekstensi inti ---
# Dapat dikompilasi TANPA pustaka sistem, jadi selalu berhasil.
# (mbstring, curl, xml, dom, fileinfo, tokenizer, openssl sudah bawaan image.)
RUN docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        exif \
        pcntl

# --- Ekstensi gambar & internasionalisasi (best effort) ---
# Butuh pustaka sistem dari apt (libpng, libjpeg, libicu, libzip).
# Pada sebagian lingkungan build, repositori apt tidak dapat dijangkau — maka
# langkah ini dibuat best effort: bila gagal, build tetap lanjut.
# Dampaknya bila terlewat: konversi gambar (thumbnail/WebP) belum tersedia.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    || echo "PERINGATAN: apt tidak dapat dijangkau - gd/intl/zip dilewati."

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Pengguna yang UID-nya sama dengan pengguna host ---
# Tujuannya agar berkas yang dibuat di dalam container (cache, log, media)
# tetap bisa ditulis dan dibaca dari sisi host tanpa masalah izin.
RUN groupadd -g ${GID} app 2>/dev/null || true \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash app 2>/dev/null || true

# --- Konfigurasi PHP ---
COPY docker/dev/php.ini /usr/local/etc/php/conf.d/zz-raab.ini

WORKDIR /var/www/html

USER app
