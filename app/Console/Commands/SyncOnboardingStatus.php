<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Command;

class SyncOnboardingStatus extends Command
{
    protected $signature = 'onboarding:sync';
    protected $description = 'Auto-detect onboarding completion for all restaurants';

    public function handle(): int
    {
        $owners = User::whereHas('restaurant')
            ->whereJsonLength('onboarding_status', '<', 5)
            ->orWhereNull('onboarding_status')
            ->with('restaurant')
            ->get();

        $updated = 0;

        foreach ($owners as $owner) {
            $rid = $owner->restaurant_id;
            if (!$rid) continue;

            $status = $owner->onboarding_status ?? [];

            if (empty($status['day_1']) && \App\Models\Product::where('restaurant_id', $rid)->exists()) {
                $status['day_1'] = ['completed_at' => now()->toIso8601String()];
            }

            if (empty($status['day_2']) && \App\Models\RestaurantTable::where('restaurant_id', $rid)->count() >= 3) {
                $status['day_2'] = ['completed_at' => now()->toIso8601String()];
            }

            if (empty($status['day_3']) && \App\Models\Ingredient::where('restaurant_id', $rid)->exists()) {
                $status['day_3'] = ['completed_at' => now()->toIso8601String()];
            }

            if (empty($status['day_4']) && \App\Models\Employee::where('restaurant_id', $rid)->where('status', 'active')->count() >= 2) {
                $status['day_4'] = ['completed_at' => now()->toIso8601String()];
            }

            if (empty($status['day_5'])) {
                $plan = $owner->restaurant?->plan;
                if ($plan && $plan->code !== 'free') {
                    $status['day_5'] = ['completed_at' => now()->toIso8601String()];
                }
            }

            if ($status !== ($owner->onboarding_status ?? [])) {
                $owner->update(['onboarding_status' => $status]);
                $updated++;
            }
        }

        $this->info("Synced onboarding for {$updated} owners.");
        return self::SUCCESS;
    }
}
