<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireOutdatedPromotions extends Command
{
    protected $signature = 'promotions:expire-outdated';
    protected $description = 'Deactivate promotions that have passed their end date';

    public function handle()
    {
        $this->info('Starting checking for outdated promotions...');

        $expiredCount = Promotion::where('is_active', true)
            ->where('end_date', '<', Carbon::today())
            ->update(['is_active' => false]);

        $this->info("Completed. Deactivated {$expiredCount} outdated promotions.");
        return Command::SUCCESS;
    }
}
