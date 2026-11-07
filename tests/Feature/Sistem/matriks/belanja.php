<?php

declare(strict_types=1);

// Matriks otorisasi domain belanja. Nilai '*' berarti semua pengguna masuk.
return [
    'GET /api/v1/keranjang' => '*',
    'DELETE /api/v1/keranjang' => '*',
    'PUT /api/v1/keranjang/butir/{produkId}' => '*',
    'DELETE /api/v1/keranjang/butir/{produkId}' => '*',
];
