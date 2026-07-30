<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\Onboarding\RestaurantOnboardingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SeedRestaurantDefaultDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $restaurantId,
        public int $userId
    ) {}

    public function handle(RestaurantOnboardingService $onboardingService): void
    {
        $restaurant = Restaurant::find($this->restaurantId);
        $user = User::find($this->userId);

        if (!$restaurant || !$user) {
            Log::warning('SeedRestaurantDefaultDataJob: restaurant or user not found', [
                'restaurant_id' => $this->restaurantId,
                'user_id' => $this->userId
            ]);
            return;
        }

        try {
            $onboardingService->seedDefaults($restaurant, $user);
        } catch (\Throwable $e) {
            Log::error('SeedRestaurantDefaultDataJob failed', [
                'restaurant_id' => $this->restaurantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
