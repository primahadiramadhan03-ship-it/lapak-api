<?php

declare(strict_types=1);

namespace App\Exceptions;

final class KonflikIdempotensi extends GalatDomain
{
    public function __construct(string $pesan = 'Kunci idempotensi sudah pernah digunakan untuk permintaan dengan isi berbeda.')
    {
        parent::__construct(
            kode: 'LP-11',
            pesan: $pesan,
            statusHttp: 409,
        );
    }
}
