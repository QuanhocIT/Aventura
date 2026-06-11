<?php

use App\Services\BillingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

Artisan::command('billing:sync-statuses', function (BillingService $billing) {
    $billing->markExpiredAndSuspended();
    $this->info('Billing statuses synced.');
})->purpose('Sync expired and suspended tenant statuses');

Artisan::command('billing:send-reminders', function (BillingService $billing) {
    $sent = $billing->sendExpiryReminders();
    $this->info("Billing reminders queued: {$sent}");
})->purpose('Queue reminders for subscriptions nearing expiration');

// Scheduling
app(Schedule::class)->command('billing:send-reminders')->dailyAt('08:00');
app(Schedule::class)->command('billing:sync-statuses')->hourly();
app(Schedule::class)->command('reports:generate-daily')->dailyAt('23:59');
app(Schedule::class)->command('dashboard:send-scheduled-reports')->dailyAt('07:30');
app(Schedule::class)->command('news:publish-scheduled')->everyFiveMinutes();

app(Schedule::class)->call(function () {
    app(\App\Services\SupportPortalService::class)->evaluateAlerts();
})->everyFiveMinutes();

app(Schedule::class)->command('services:check-health')->everyFiveMinutes();

app(Schedule::class)->command('db:backup')->dailyAt('02:00');
app(Schedule::class)->command('db:optimize')->weeklyOn(0, '03:00');

