<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * LP-13: Saldo dompet tidak mencukupi untuk mutasi yang diminta.
 * Turun dari GalatDomain agar dirender sebagai JSON 409, bukan 500.
 */
final class SaldoTidakCukup extends GalatDomain
{
    public function __construct(int $saldo, int $jumlah)
    {
        parent::__construct(
            'LP-13',
            "Saldo {$saldo} tidak cukup untuk memindahkan {$jumlah}.",
            409,
            ['saldo' => $saldo, 'diminta' => $jumlah],
        );
    }
}
