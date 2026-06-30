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
        $schedule->command('restaurants:calculate-health')->dailyAt('23:15');
        $schedule->command('tickets:check-sla')->everyFiveMinutes();
        
        $schedule->command('reservations:send-reminders')->everyThirtyMinutes();
        $schedule->command('reservations:mark-no-shows')->everyFifteenMinutes();
        $schedule->command('promotions:expire-outdated')->dailyAt('00:01');
        $schedule->command('shifts:auto-close-expired')->dailyAt('23:45');
        $schedule->command('kitchen:alert-overdue-orders')->everyFiveMinutes();
        $schedule->command('trial:onboarding-emails')->dailyAt('09:00');
        $schedule->command('onboarding:sync')->everyThirtyMinutes();
        $schedule->command('kpis:recalculate')->dailyAt('02:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
