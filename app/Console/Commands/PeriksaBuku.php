<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BukuBesar;
use Illuminate\Console\Command;

final class PeriksaBuku extends Command
{
    protected $signature = 'lapak:periksa-buku';

    protected $description = 'Memverifikasi keseimbangan buku besar (LP-13)';

    public function handle(BukuBesar $bukuBesar): int
    {
        if ($bukuBesar->periksaKeseimbangan()) {
            $this->info('Buku besar seimbang. Total mutasi: 0');

            return self::SUCCESS;
        }

        $this->error('Buku besar TIDAK seimbang! Ada mutasi yang hilang.');

        return self::FAILURE;
    }
}
