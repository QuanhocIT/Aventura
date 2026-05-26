<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscription:send-reminders';

    protected $description = 'Legacy alias for billing reminder dispatch.';

    public function handle(BillingService $billing): int
    {
        $sent = $billing->sendExpiryReminders();

        $this->info("Billing reminders queued: {$sent}");

        return self::SUCCESS;
    }
}
