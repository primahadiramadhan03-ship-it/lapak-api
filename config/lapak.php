<?php

return [
    'komisi' => (float) env('LAPAK_KOMISI', 0.05),
    'komisi_persen' => (int) env('LAPAK_KOMISI_PERSEN', 5),
    'batas_waktu_bayar_menit' => env('LAPAK_BATAS_WAKTU_BAYAR_MENIT', 15),
    'batas_bayar_jam' => (int) env('LAPAK_BATAS_BAYAR_JAM', 24),
    'lama_tahan_hari' => env('LAPAK_LAMA_TAHAN_HARI', 3),
    'minimal_pencairan' => env('LAPAK_MINIMAL_PENCAIRAN', 10000),

    // Gerbang pembayaran (disimulasikan). Rahasia bersama penanda tangan webhook: wajib diisi
    // lewat .env dan tidak pernah di-commit. Bila kosong, seluruh webhook ditolak.
    'gateway' => [
        'rahasia' => env('LAPAK_GATEWAY_RAHASIA'),
        'toleransi_detik' => 300,
    ],

    'batas_katalog' => env('LAPAK_BATAS_KATALOG', 50),
    'batas_keranjang' => env('LAPAK_BATAS_KERANJANG', 20),
    'katalog' => [
        'per_halaman_bawaan' => 12,
        'per_halaman_maksimum' => 60,
    ],
    'belanja' => [
        'butir_maksimum' => 10,
        'kuantitas_maksimum' => 99,
    ],

    // LP-14 dan LP-15: umur kunci sesi.
    'sesi' => [
        'masa_hidup_hari' => (int) env('LAPAK_SESI_MASA_HIDUP_HARI', 7),
        'menganggur_menit' => (int) env('LAPAK_SESI_MENGANGGUR_MENIT', 120),
        'segar_menit' => (int) env('LAPAK_SESI_SEGAR_MENIT', 10),
        // terakhir_dipakai_pada tidak ditulis ulang lebih sering dari ini.
        'jeda_sentuh_detik' => 60,
    ],

    'transaksi' => [
        'batas_segar_rupiah' => (int) env('LAPAK_BATAS_SEGAR_RUPIAH', 2_000_000),
        'laju_per_menit' => 10,
        'alamat_maksimum' => 5,
    ],

    'otorisasi' => [
        // Lama izin seorang pengguna diingat. Perubahan peran melupakannya saat itu juga;
        // angka ini hanya batas atas bila ada jalur yang lupa memanggil lupakan().
        'ingat_menit' => (int) env('LAPAK_IZIN_INGAT_MENIT', 5),
    ],

    // Batas percobaan per menit untuk pintu yang menerima kata sandi.
    'laju' => [
        'masuk_per_akun' => 5,
        'masuk_per_ip' => 20,
        'konfirmasi' => 5,
    ],
];
