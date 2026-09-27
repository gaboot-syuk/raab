<?php

/*
 * Public aspiration page.
 */
return [
    'judul' => 'Aspirations',
    'intro' => 'Send your aspirations to the rayon board, and see how other aspirations were answered.',
    'galat_ringkas' => 'Please check the following fields:',

    'papan' => [
        'judul' => 'Aspiration Board',
        'teks' => 'Only aspirations that have been answered appear here. Sender identities are never shown, and the body text has been cleaned of phone numbers, emails, and national ID numbers.',
        'total' => 'Answered',
        'selesai' => 'Completed',
        'diproses' => 'In Progress',
        'kosong' => 'No aspirations have been answered yet.',
        'kosong_teks' => 'This board fills up as soon as the board answers incoming aspirations.',
        'tanggapan' => 'Board Response',
        'anonim' => 'Sender identity is readable by the board only.',
        'kategori' => 'Category',
        'semua_kategori' => 'All categories',
        'saring' => 'Filter',
    ],

    'form' => [
        'judul' => 'Submit an Aspiration',
        'teks' => 'Identity is required so the board can follow up and contact you. Your identity will NOT be shown on the public board.',
        'nama' => 'Name *',
        'email' => 'Email *',
        'telepon' => 'Phone number',
        'kategori' => 'Category *',
        'judul_aspirasi' => 'Title *',
        'isi' => 'Your aspiration *',
        'isi_bantuan' => 'Describe the situation clearly: what, where, and what you expect. Avoid writing other people\'s phone numbers or personal data in the body — that part is cleaned before publication.',
        'min_isi' => 'At least :jumlah characters.',
        'tampil_publik' => 'Allow this aspiration to appear on the public board once answered',
        'kirim' => 'Send Aspiration',
        'terkirim' => 'Aspiration Sent',
        'token_judul' => 'Save this ticket number and token',
        'token_teks' => 'Both are needed to track the follow-up. The token is shown only once on this page and is not emailed to you, so copy it now.',
        'nomor_tiket' => 'Ticket number',
        'token' => 'Tracking token',
    ],

    'lacak' => [
        'judul' => 'Track an Aspiration',
        'teks' => 'Enter the ticket number and token you received when submitting.',
        'nomor_tiket' => 'Ticket number *',
        'token' => 'Tracking token *',
        'tombol' => 'Track',
        'judul_hasil' => 'Tracking Result',
        'status' => 'Status',
        'dikirim' => 'Sent',
        'ditanggapi' => 'Answered',
        'belum_ditanggapi' => 'Not yet answered by the board.',
        'isi_kamu' => 'Your aspiration',
    ],
];
