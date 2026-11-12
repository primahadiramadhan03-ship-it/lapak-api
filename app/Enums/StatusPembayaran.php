<?php

declare(strict_types=1);

namespace App\Enums;

enum StatusPembayaran: string
{
    case Menunggu = 'menunggu';
    case Lunas = 'lunas';
    case Kedaluwarsa = 'kedaluwarsa';
    case Dibatalkan = 'dibatalkan';

    public function akhir(): bool
    {
        return $this !== self::Menunggu;
    }
}
