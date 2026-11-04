<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PangkasSesi as PangkasSesiService;
use Illuminate\Console\Command;

final class PangkasSesi extends Command
{
    protected $signature = 'lapak:pangkas-sesi';

    protected $description = 'Pangkas sesi kedaluwarsa dan menganggur dari basis data';

    public function __invoke(PangkasSesiService $pangkas): int
    {
        $hapus = $pangkas->jalankan();

        $this->info("Sesi yang dihapus: {$hapus}");

        return Command::SUCCESS;
    }
}
