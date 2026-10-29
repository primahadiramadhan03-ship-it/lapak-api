
<?php

return [
    'komisi' => env('LAPAK_KOMISI', 0.05),
    'batas_waktu_bayar_menit' => env('LAPAK_BATAS_WAKTU_BAYAR_MENIT', 15),
    'lama_tahan_hari' => env('LAPAK_LAMA_TAHAN_HARI', 3),
    'minimal_pencairan' => env('LAPAK_MINIMAL_PENCAIRAN', 10000),
    'batas_katalog' => env('LAPAK_BATAS_KATALOG', 50),
    'batas_keranjang' => env('LAPAK_BATAS_KERANJANG', 20),

    // LP-14 dan LP-15: umur kunci sesi.
    'sesi' => [
        'masa_hidup_hari' => (int) env('LAPAK_SESI_MASA_HIDUP_HARI', 7),
        'menganggur_menit' => (int) env('LAPAK_SESI_MENGANGGUR_MENIT', 120),
        'segar_menit' => (int) env('LAPAK_SESI_SEGAR_MENIT', 10),
        // terakhir_dipakai_pada tidak ditulis ulang lebih sering dari ini.
        'jeda_sentuh_detik' => 60,
    ],

    // Batas percobaan per menit untuk pintu yang menerima kata sandi.
    'laju' => [
        'masuk_per_akun' => 5,
        'masuk_per_ip' => 20,
        'konfirmasi' => 5,
    ],
];
