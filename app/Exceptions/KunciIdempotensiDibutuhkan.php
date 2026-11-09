<?php

declare(strict_types=1);

namespace App\Exceptions;

final class KunciIdempotensiDibutuhkan extends GalatDomain
{
    public function __construct(string $pesan = 'Header Idempotency-Key wajib disertakan pada permintaan ini.')
    {
        parent::__construct(
            kode: 'LP-11',
            pesan: $pesan,
            statusHttp: 400,
        );
    }
}
