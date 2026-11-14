<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PenyelesaianOtomatis;
use Illuminate\Console\Command;

final class SelesaikanOtomatis extends Command
{
    protected $signature = 'lapak:selesaikan-otomatis';

    protected $description = 'Menyelesaikan pesanan yang melewati masa tahan dan melepas dananya (LP-12)';

    public function handle(PenyelesaianOtomatis $penyelesaian): int
    {
        $jumlah = $penyelesaian->jalankan();

        $this->info("{$jumlah} pesanan diselesaikan dan dananya dilepas ke penjual.");

        return self::SUCCESS;
    }
}

