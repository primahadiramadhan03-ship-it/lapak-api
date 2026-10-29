<?php

return [
    'komisi_persen' => env('LAPAK_KOMISI', 5),
    'batas_waktu_bayar_menit' => env('LAPAK_BATAS_WAKTU_BAYAR_MENIT', 15),
    'lama_tahan_hari' => env('LAPAK_LAMA_TAHAN_HARI', 3),
    'minimal_pencairan' => env('LAPAK_MINIMAL_PENCAIRAN', 10000),
    'batas_katalog' => env('LAPAK_BATAS_KATALOG', 50),
    'batas_keranjang' => env('LAPAK_BATAS_KERANJANG', 20),
    'belanja' => [
        'butir_maksimum' => 10,
        'kuantitas_maksimum' => 100,
    ],
];