<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogDiscountAppliedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $orderId,
        private int $userId,
        private array $oldValues,
        private array $newValues,
        private ?string $ipAddress,
        private ?string $userAgent
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        $user = User::find($this->userId);

        if (! $order || ! $user) {
            return;
        }

        AuditLog::create([
            'restaurant_id' => $order->restaurant_id,
            'branch_id' => $order->branch_id,
            'user_id' => $user->id,
            'user_role' => $user->roles()->pluck('name')->first() ?? 'staff',
            'event' => 'updated',
            'action' => 'discount_applied',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'ip_address' => $this->ipAddress ?? '127.0.0.1',
            'user_agent' => $this->userAgent ?? 'unknown',
            'created_at' => now(),
        ]);
    }
}
