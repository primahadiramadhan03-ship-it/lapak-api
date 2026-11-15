<?php

declare(strict_types=1);

return [
    'berkunci' => [
        'POST api/v1/dompet/pencairan',
        'POST api/v1/keuangan/pencairan/{kode}/setujui',
        'POST api/v1/keuangan/pencairan/{kode}/tolak',
    ],
    'tanpa_kunci' => [
        'POST api/v1/webhook/pembayaran' => 'Dijaga status tagihan di bawah gembok checkout.',
    ],
];
