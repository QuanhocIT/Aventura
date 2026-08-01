<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\EmailMicroserviceClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReviewRequestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public readonly int $orderId
    ) {}

    public function handle(EmailMicroserviceClient $client): void
    {
        $order = Order::withoutGlobalScopes()->with(['restaurant', 'customer'])->find($this->orderId);

        if (! $order) {
            Log::warning("SendReviewRequestEmail: Order ID {$this->orderId} not found.");

            return;
        }

        if ($order->status !== 'completed') {
            Log::warning("SendReviewRequestEmail: Order ID {$this->orderId} status is not completed. Current: {$order->status}");

            return;
        }

        $customer = $order->customer;
        if (! $customer || empty($customer->email)) {
            Log::warning("SendReviewRequestEmail: Order ID {$this->orderId} has no customer or customer has no email.");

            return;
        }

        $restaurant = $order->restaurant;
        $restaurantName = $restaurant ? $restaurant->name : 'Aventura Restaurant';

        // Prepare review request data
        $sent = $client->sendReviewRequest([
            'recipient_email' => $customer->email,
            'recipient_name' => $customer->full_name ?? 'Quý khách',
            'restaurant_name' => $restaurantName,
            'review_url' => url("/reviews/order/{$order->id}"),
        ]);

        if ($sent) {
            Log::info("SendReviewRequestEmail: Successfully sent review request email for order ID {$order->id}");
        } else {
            Log::error("SendReviewRequestEmail: Failed to send review request email for order ID {$order->id}");
        }
    }

    public function tags(): array
    {
        return ["order:{$this->orderId}", 'review-request'];
    }
}
