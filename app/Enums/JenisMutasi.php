<?php
declare(strict_types=1);
namespace App\Enums;

enum JenisMutasi: string 
{
    case DanaMasuk = 'dana_masuk';
    case LepasDana = 'lepas_dana';
    case Komisi = 'komisi';
    case Pengembalian = 'pengembalian';
    case Pencairan = 'pencairan';
}