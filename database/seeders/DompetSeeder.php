<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisDompet;
use App\Models\Dompet;
use Illuminate\Database\Seeder;

final class DompetSeeder extends Seeder
{
    public function run(): void
    {
        // LP-13: Buat 3 dompet sistem (eksternal, escrow, pendapatan)
        Dompet::buat('eksternal', JenisDompet::Eksternal);
        Dompet::buat('escrow', JenisDompet::Escrow);
        Dompet::buat('pendapatan', JenisDompet::Pendapatan);
    }
}
