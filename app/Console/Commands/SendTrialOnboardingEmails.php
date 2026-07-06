<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Notifications\TrialOnboardingNotification;
use Illuminate\Console\Command;

class SendTrialOnboardingEmails extends Command
{
    protected $signature = 'trial:onboarding-emails';
    protected $description = 'Send onboarding emails to trial users at Day 1, 3, 7, 12';

    public function handle(): int
    {
        $milestones = [1, 3, 7, 12];

        foreach ($milestones as $day) {
            $targetDate = now()->subDays($day)->toDateString();

            $restaurants = Restaurant::where('status', 'active')
                ->whereDate('trial_ends_at', now()->addDays(14 - $day)->toDateString())
                ->whereHas('owner')
                ->with('owner')
                ->get();

            foreach ($restaurants as $restaurant) {
                $owner = $restaurant->owner;
                if (!$owner) {
                    continue;
                }

                $cacheKey = "trial_onboarding:{$restaurant->id}:day{$day}";
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    continue;
                }

                try {
                    $owner->notify(new TrialOnboardingNotification($day, $restaurant->name));
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addDays(15));
                    $this->info("Sent Day {$day} email to {$owner->email} ({$restaurant->name})");
                } catch (\Throwable $e) {
                    $this->error("Failed for {$owner->email}: {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
