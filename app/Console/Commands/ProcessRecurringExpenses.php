<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Models\OperatingExpense;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringExpenses extends Command
{
    protected $signature = 'expenses:process-recurring';
    protected $description = 'Process active recurring expenses and generate operating expenses';

    public function handle()
    {
        $today = today();
        $this->info("Processing recurring expenses for: " . $today->toDateString());

        $recurringExpenses = RecurringExpense::where('is_active', true)
            ->where('start_date', '<=', $today->toDateString())
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today->toDateString());
            })
            ->get();

        $count = 0;
        foreach ($recurringExpenses as $recurring) {
            // Determine first trigger date
            $nextTrigger = $recurring->last_triggered_at 
                ? $this->getNextTriggerDate(Carbon::parse($recurring->last_triggered_at), $recurring->frequency)
                : Carbon::parse($recurring->start_date);

            $updated = false;

            // Catch up loop (backfill if scheduler was down)
            while ($nextTrigger->isPast() || $nextTrigger->isToday()) {
                // Verify end_date boundary
                if ($recurring->end_date && $nextTrigger->greaterThan(Carbon::parse($recurring->end_date))) {
                    break;
                }

                // Avoid duplication for the exact date
                $exists = OperatingExpense::where('recurring_expense_id', $recurring->id)
                    ->where('expense_date', $nextTrigger->toDateString())
                    ->exists();

                if (!$exists) {
                    OperatingExpense::create([
                        'restaurant_id' => $recurring->restaurant_id,
                        'category_id' => $recurring->category_id,
                        'recurring_expense_id' => $recurring->id,
                        'amount' => $recurring->amount,
                        'expense_date' => $nextTrigger->toDateString(),
                        'description' => "Tự động phát sinh từ chi phí định kỳ: {$recurring->name}",
                        'created_by' => null,
                    ]);
                    $count++;
                }

                $recurring->last_triggered_at = $nextTrigger->toDateString();
                $updated = true;

                // Shift to the next scheduled date
                $nextTrigger = $this->getNextTriggerDate($nextTrigger, $recurring->frequency);
            }

            if ($updated) {
                $recurring->save();
            }
        }

        $this->info("Finished. Created {$count} operating expenses.");
    }

    private function getNextTriggerDate(Carbon $date, string $frequency): Carbon
    {
        $newDate = $date->copy();
        return match ($frequency) {
            'weekly' => $newDate->addWeek(),
            'monthly' => $newDate->addMonth(),
            'quarterly' => $newDate->addMonths(3),
            'yearly' => $newDate->addYear(),
            default => $newDate->addMonth(),
        };
    }
}
