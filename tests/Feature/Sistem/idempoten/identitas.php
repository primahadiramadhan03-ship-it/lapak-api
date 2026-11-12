<?php

declare(strict_types=1);

return [
    'berkunci' => [],
    'tanpa_kunci' => [
        'POST api/v1/registrasi' => 'Surel unik: pengulangan dijawab 422, tidak membuat akun kedua.',
        'POST api/v1/masuk' => 'Pengulangan hanya menerbitkan sesi tambahan yang dapat dicabut.',
        'POST api/v1/keluar' => 'Mencabut sesi yang sudah dicabut tidak mengubah apa pun.',
        'POST api/v1/sesi/konfirmasi' => 'Kunci lama mati setelah berhasil; pengulangan dijawab 401.',
        'POST api/v1/alamat' => 'Tidak menyentuh uang maupun stok; jumlah alamat dibatasi.',
    ],
];
