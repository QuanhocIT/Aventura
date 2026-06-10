<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send push notification to a user.
     */
    public function send(User $user, string $title, string $content): bool
    {
        if (empty($user->device_token)) {
            Log::info("PushNotificationService: User ID {$user->id} ({$user->name}) has no device token. Skipping push notification.");
            return false;
        }

        Log::info("PushNotificationService: Sending push notification to User ID {$user->id} ({$user->name}) with token '{$user->device_token}'", [
            'title' => $title,
            'content' => $content,
        ]);

        // Note: Real Firebase Cloud Messaging (FCM) integration would perform a post request:
        // Http::withHeaders(['Authorization' => 'key=' . config('services.fcm.key')])->post('https://fcm.googleapis.com/fcm/send', [...]);
        
        return true;
    }
}
