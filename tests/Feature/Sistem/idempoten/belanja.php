<?php

declare(strict_types=1);

return [
    'berkunci' => [
        'POST api/v1/checkout',
        'POST api/v1/pesanan/{nomor}/terima',
        'POST api/v1/pesanan/{nomor}/batal',
        'POST api/v1/checkout/{kode}/batal',
    ],
    'tanpa_kunci' => [
        'POST api/v1/simulasi-belanja' => 'Hanya menghitung, tidak menulis.',
    ],
];
