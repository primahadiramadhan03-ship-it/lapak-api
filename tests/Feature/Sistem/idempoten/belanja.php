<?php

declare(strict_types=1);

return [
    'berkunci' => [
        'POST api/v1/checkout',
    ],
    'tanpa_kunci' => [
        'POST api/v1/simulasi-belanja' => 'Hanya menghitung, tidak menulis.',
    ],
];
