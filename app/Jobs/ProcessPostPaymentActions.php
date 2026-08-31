<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\CdpService;
use App\Services\InventoryService;
use App\Services\LoyaltyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPostPaymentActions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        $user = User::find($this->userId);

        if (! $order || ! $user) {
            Log::warning("ProcessPostPaymentActions: Order {$this->orderId} or User {$this->userId} not found.");

            return;
        }

        Log::info("ProcessPostPaymentActions job running for order #{$order->order_number}");

        // 1. Trừ kho theo BOM
        app(InventoryService::class)->deductInventoryForOrder($order, $user);

        // 2. Tính điểm thành viên & Cập nhật RFM
        if ($order->customer_id) {
            $customer = Customer::find($order->customer_id);
            if ($customer) {
                $customer->update(['last_order_at' => now()]);

                $loyaltyService = app(LoyaltyService::class);
                $loyaltyService->earnPoints($customer, $order, (float) $order->total_amount);
                $loyaltyService->recalculateTier($customer);

                CdpService::calculateRfmForCustomer($customer, $order->branch_id);
            }
        }
    }
}
