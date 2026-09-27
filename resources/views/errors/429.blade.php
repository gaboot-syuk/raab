@include('errors.minimal', [
    'kode' => 429,
    'pesan' => 'Terlalu banyak permintaan dalam waktu singkat.',
    'saran' => 'Tunggu sebentar, lalu coba lagi. Pembatasan ini melindungi situs dari kiriman beruntun yang tidak wajar.',
])
