<?php

namespace App\Jobs\SuperAdmin;

use App\Events\SuperAdmin\NotificationCampaignBroadcasted;
use App\Models\NotificationCampaign;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\EmailMicroserviceClient;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout for bulk operations
    public int $uniqueFor = 900;

    public function __construct(protected NotificationCampaign $campaign) {}

    public function uniqueId(): string
    {
        return 'notification-campaign:'.$this->campaign->getKey();
    }

    public function handle(
        EmailMicroserviceClient $emailClient,
        PushNotificationService $pushService
    ): void {
        $this->campaign->refresh();
        if ($this->campaign->status !== 'sending') {
            return;
        }

        $this->campaign->update(['status' => 'sending']);

        try {
            // 1. Resolve Target Restaurants
            $restaurantQuery = Restaurant::query()
                ->whereNull('deleted_at')
                ->where('status', 'active');

            if ($this->campaign->target_type === 'plan') {
                $restaurantQuery->where('plan_id', $this->campaign->target_plan_id);
            } elseif ($this->campaign->target_type === 'trial') {
                $restaurantQuery->whereNotNull('trial_ends_at')
                    ->where('trial_ends_at', '>=', now());
            }

            $targetRestaurantIds = $restaurantQuery->pluck('id')->toArray();
            $targetOwnerIds = $restaurantQuery->whereNotNull('owner_user_id')
                ->pluck('owner_user_id')
                ->toArray();

            // 2. Resolve Target Users
            $userQuery = User::query()
                ->where('status', 'active')
                ->whereNotNull('restaurant_id');

            // Target by role inside target restaurants
            if ($this->campaign->target_role === 'owner') {
                // Only restaurant owners
                $userQuery->where(function ($q) use ($targetOwnerIds) {
                    $q->whereIn('id', $targetOwnerIds)
                        ->orWhere(function ($sq) {
                            $sq->whereHas('roles', function ($rq) {
                                $rq->where('name', 'owner');
                    });
                });
                });

                // Restrict to targeted restaurants if target_type is not 'all'
                $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
            } else {
                // All staff/employees
                $userQuery->whereIn('restaurant_id', $targetRestaurantIds);
            }

            $targetUsers = $userQuery->get();
            $sentCount = 0;
            $channels = $this->campaign->channels ?? [];

            Log::info("SendNotificationCampaignJob: Campaign #{$this->campaign->id} resolved target users: ".$targetUsers->count());

            // 3. Deliver Websocket (Real-time Laravel Reverb)
            // Broadcast only to tenant channels that are already in the campaign audience.
            if (in_array('websocket', $channels)) {
                broadcast(new NotificationCampaignBroadcasted($this->campaign, $targetRestaurantIds))->toOthers();
                Log::info("SendNotificationCampaignJob: Broadcasted websocket event for Campaign #{$this->campaign->id}");
            }

            // 4. Deliver Emails
            if (in_array('email', $channels)) {
                foreach ($targetUsers as $user) {
                    if (! empty($user->email)) {
                        $emailClient->sendCampaignEmail([
                            'email' => $user->email,
                            'name' => $user->name,
                            'title' => $this->campaign->title,
                            'content' => $this->campaign->content,
                        ]);
                        $sentCount++;
                    }
                }
            }

            // 5. Deliver Push Notifications
            if (in_array('push', $channels)) {
                foreach ($targetUsers as $user) {
                    if (! empty($user->device_token)) {
                        $pushService->send($user, $this->campaign->title, $this->campaign->content);
                        if (! in_array('email', $channels)) {
                            // If email wasn't sent, increment sent count for push
                            $sentCount++;
                        }
                    }
                }
            }

            // If only websocket was sent, count target users as sent count
            if (empty($sentCount) && in_array('websocket', $channels)) {
                $sentCount = $targetUsers->count();
            }

            // Update campaign record
            $this->campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
                'sent_at' => now(),
            ]);

            Log::info("SendNotificationCampaignJob: Campaign #{$this->campaign->id} completed successfully.");

        } catch (\Throwable $e) {
            Log::error('SendNotificationCampaignJob failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);
            $this->campaign->update(['status' => 'failed']);
            throw $e;
        }
    }
}
