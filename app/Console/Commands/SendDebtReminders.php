<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Mail\DebtReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendDebtReminders extends Command
{
    protected $signature = 'debts:send-reminders';
    protected $description = 'Send daily debt reminder emails for payables near due or overdue (to owners) and receivables (to customers)';

    public function handle()
    {
        $this->info('Starting debt reminder scanning...');
        $today = now()->startOfDay();

        // 1. Process Payables (Supplier Debt) -> Notify Restaurant Owners
        $payables = AccountPayable::where('status', '!=', 'paid')
            ->with(['supplier', 'purchaseOrder', 'restaurant.owner'])
            ->get();

        $payableSent = 0;
        foreach ($payables as $payable) {
            $dueDate = \Carbon\Carbon::parse($payable->due_date)->startOfDay();
            $daysToDueDateVal = (int) $today->diffInDays($dueDate, false);

            // Notify if due in exactly 3 days, or already overdue
            if ($daysToDueDateVal === 3 || $daysToDueDateVal < 0) {
                // Get owner email
                $ownerEmail = $payable->restaurant->owner->email ?? $payable->restaurant->email ?? null;
                if (!$ownerEmail) {
                    continue;
                }

                $cacheKey = "debt_reminder_sent_payable_{$payable->id}_" . today()->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                try {
                    Mail::to($ownerEmail)->send(new DebtReminderMail($payable, 'payable', $daysToDueDateVal));
                    Cache::put($cacheKey, true, now()->addHours(24));
                    $payableSent++;
                } catch (\Throwable $e) {
                    Log::error("Failed to send payable reminder email to owner {$ownerEmail} for payable ID {$payable->id}: " . $e->getMessage());
                }
            }
        }

        // 2. Process Receivables (Customer Debt) -> Notify Customers
        $receivables = AccountReceivable::where('status', '!=', 'paid')
            ->with(['customer', 'order', 'restaurant'])
            ->get();

        $receivableSent = 0;
        foreach ($receivables as $receivable) {
            $dueDate = \Carbon\Carbon::parse($receivable->due_date)->startOfDay();
            $daysToDueDateVal = (int) $today->diffInDays($dueDate, false);

            // Notify if due in exactly 3 days, or already overdue
            if ($daysToDueDateVal === 3 || $daysToDueDateVal < 0) {
                $customerEmail = $receivable->customer->email ?? null;
                if (!$customerEmail) {
                    continue;
                }

                $cacheKey = "debt_reminder_sent_receivable_{$receivable->id}_" . today()->toDateString();
                if (Cache::has($cacheKey)) {
                    continue;
                }

                try {
                    Mail::to($customerEmail)->send(new DebtReminderMail($receivable, 'receivable', $daysToDueDateVal));
                    Cache::put($cacheKey, true, now()->addHours(24));
                    $receivableSent++;
                } catch (\Throwable $e) {
                    Log::error("Failed to send receivable reminder email to customer {$customerEmail} for receivable ID {$receivable->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Completed sending {$payableSent} payable reminders and {$receivableSent} receivable reminders.");
        return Command::SUCCESS;
    }
}
