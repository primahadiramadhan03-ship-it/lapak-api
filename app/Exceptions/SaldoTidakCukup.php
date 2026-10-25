<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class SaldoTidakCukup extends RuntimeException
{
    public function __construct(int $saldo, int $jumlah)
    {
        parent::__construct("Saldo {$saldo} tidak cukup untuk memindahkan {$jumlah}.");
    }
}
