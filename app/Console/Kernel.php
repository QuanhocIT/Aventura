<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('billing:send-reminders')->dailyAt('08:00');
        $schedule->command('billing:sync-statuses')->hourly();
        $schedule->command('reports:generate-daily')->dailyAt('23:59');
        $schedule->command('restaurants:validate-activity')->dailyAt('23:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
