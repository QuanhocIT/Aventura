<?php

namespace App\Jobs;

use App\Models\Order;
use App\Events\QrOrderTimeoutEscalated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckQrOrderTimeout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        // Bypass global tenant scope for back-end job lookups
        $currentOrder = Order::withoutGlobalScope('restaurant')->find($this->order->id);

        if ($currentOrder && $currentOrder->status === 'pending') {
            // Establish TenantContext in queue runner thread
            app(\App\Support\Tenant\TenantContext::class)->setRestaurantId($currentOrder->restaurant_id);

            event(new QrOrderTimeoutEscalated($currentOrder));
        }
    }
}
