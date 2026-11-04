<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PangkasSesi;
use Illuminate\Console\Command;

final class PangkasSesiCommand extends Command
{
    protected $signature = 'sesi:pangkas';

    protected $description = 'Pangkas sesi kedaluwarsa dan menganggur dari basis data';

    public function __invoke(PangkasSesi $pangkas): int
    {
        $hapus = $pangkas->jalankan();

        $this->info("Sesi yang dihapus: {$hapus}");

        return Command::SUCCESS;
    }
}
