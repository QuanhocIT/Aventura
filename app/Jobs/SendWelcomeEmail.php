<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailMicroserviceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $userId) {}

    public function handle(EmailMicroserviceClient $client): void
    {
        $user = User::query()->with('restaurant')->find($this->userId);

        if (! $user) {
            Log::warning('SendWelcomeEmail: user không tồn tại', ['user_id' => $this->userId]);
            return;
        }

        $restaurant = $user->restaurant;
        $trialDays = $restaurant?->trial_ends_at
            ? (int) now()->diffInDays($restaurant->trial_ends_at, false)
            : 14;

        $client->sendWelcome([
            'name'            => $user->name,
            'email'           => $user->email,
            'restaurant_name' => $restaurant?->name ?? $user->name,
            'trial_days'      => max(0, $trialDays),
            'login_url'       => config('app.url').'/login',
        ]);
    }
}
