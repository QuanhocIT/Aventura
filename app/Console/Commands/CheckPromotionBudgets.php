<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Illuminate\Console\Command;

class CheckPromotionBudgets extends Command
{
    protected $signature = 'promotions:check-budgets';

    protected $description = 'Deactivate promotions that have exhausted their budget cap';

    public function handle(): int
    {
        $deactivated = Promotion::withoutGlobalScopes()
            ->where('is_active', true)
            ->where('auto_deactivate_on_budget', true)
            ->whereNotNull('budget_cap')
            ->whereColumn('budget_spent', '>=', 'budget_cap')
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            $this->info("Deactivated {$deactivated} promotions due to exhausted budget.");
        }

        return self::SUCCESS;
    }
}
