<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\PangkasSesiCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Pangkas sesi kedaluwarsa dan menganggur setiap jam (B2).
        $schedule->command(PangkasSesiCommand::class)->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
