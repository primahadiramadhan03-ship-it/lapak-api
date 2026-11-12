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
        // LP-13: Buat dompet sistem (eksternal, escrow, pendapatan, penahan) secara idempoten
        foreach ([
            JenisDompet::Eksternal,
            JenisDompet::Escrow,
            JenisDompet::Pendapatan,
            JenisDompet::Penahan,
        ] as $jenis) {
            if (Dompet::query()->where('kode', $jenis->value)->doesntExist()) {
                Dompet::buat($jenis->value, $jenis);
            }
        }
    }
}
