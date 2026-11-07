<?php

declare(strict_types=1);

// Matriks otorisasi domain dompet. Nilai '*' berarti semua pengguna masuk.
return [
    'GET /api/v1/dompet' => '*',
    'GET /api/v1/dompet/mutasi' => '*',
];
