<?php

declare(strict_types=1);

/*
| Matriks otorisasi domain D5: log audit (LP-19).
| Auditor membaca jejak seluruh domain, tetapi tidak menulis apa pun di sini.
*/
return [
    'GET /api/v1/audit/log' => ['auditor'],
    'GET /api/v1/audit/log/{id}' => ['auditor'],
];
