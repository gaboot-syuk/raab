<?php

/*
 * Member card verification page & digital member card.
 *
 * Kept separate from `umum.php` because only these two pages use it.
 */
return [
    'judul' => 'Member Card Verification',
    'intro' => 'This page confirms that a member card was genuinely issued by the rayon and is still valid.',
    'remah' => 'Card Verification',

    'sah' => [
        'judul' => 'Card Is Valid',
        'teks' => 'This card was genuinely issued by the rayon and is still valid.',
    ],

    'tidak_sah' => [
        'judul' => 'Card Is Invalid',
        'teks' => 'This card cannot be verified.',
    ],

    'alasan' => [
        'dicabut' => 'This card has been revoked. Revocation happens when membership status changes, for example to alumnus or inactive.',
        'kedaluwarsa' => 'This card has expired. Its holder is most likely still an active member and should request a reissue at the secretariat.',
        'tidak_ditemukan' => 'No card matches that verification code. Please check the link or the QR code you scanned.',
    ],

    'bidang' => [
        'nama' => 'Name',
        'nomor' => 'Member Number',
        'nomor_kartu' => 'Card Number',
        'unit' => 'Bureau & LSO',
        'jalur' => 'Track',
        'berlaku' => 'Valid Until',
        'diterbitkan' => 'Issued',
        'tanpa_batas' => 'No expiry',
    ],

    'privasi' => 'This page only shows the information already printed on the card. Contact the rayon secretariat for further details.',
    'kembali' => 'Go to Homepage',

    'kartu' => [
        'judul' => 'Digital Member Card',        'cetak' => 'Print / Save as PDF',
        'tutup' => 'Close',
        'tautan' => 'Verification link',
        'pindai' => 'Scan the QR code to verify this card.',
        'belum_ada' => 'Your member card has not been issued yet.',
        'belum_ada_teks' => 'The card is issued automatically once your membership is verified. Contact the rayon secretariat if you believe you are already verified.',
        'alumni_tanpa_kartu' => 'Member cards do not apply to alumni.',
        'alumni_tanpa_kartu_teks' => 'The member card is used for activity attendance and borrowing, and both are for active members only. Your alumni status is still recorded, and you can manage your own alumni profile.',
        'dicabut' => 'This card has been revoked and cannot be used for attendance.',
        'kedaluwarsa' => 'This card has expired. Request a reissue at the secretariat.',
    ],

    'prestasi' => [
        'intro' => 'Achievements of rayon members that have been checked and verified by the board.',
        'jumlah' => 'Verified Achievements',
        'kader' => 'Achieving Members',
        'unggulan' => 'Rayon Highlights',
        'tingkat' => 'Level',
        'semua_tingkat' => 'All levels',
        'kategori' => 'Category',
        'semua_kategori' => 'All categories',
        'saring' => 'Filter',
        'kosong' => 'No achievements published yet.',
        'kosong_teks' => 'Achievements appear here once the board has verified them and the owner has allowed publication.',
        'lihat_sertifikat' => 'View certificate',
        'catatan' => 'Only achievements verified by the board and allowed by their owner appear on this page. Claims still awaiting review are not published.',
    ],
];
