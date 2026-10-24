<?php
declare(strict_types=1);
namespace App\Enums;

enum JenisDompet: string 
{
    case Eksternal = 'eksternal';
    case Escrow = 'escrow';
    case Pendapatan = 'pendapatan';
    case Penjual = 'penjual';

    public function bolehNegatif(): bool 
    {
        return $this === self::Eksternal;
    }
}