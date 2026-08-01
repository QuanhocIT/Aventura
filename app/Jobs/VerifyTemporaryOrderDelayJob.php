<?php

namespace App\Jobs;

use App\Events\Customer\TemporaryOrderEscalated;
use App\Models\AuditLog;
use App\Models\TemporaryOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyTemporaryOrderDelayJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $temporaryOrderId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $temporaryOrder = TemporaryOrder::find($this->temporaryOrderId);

        if (! $temporaryOrder) {
            Log::warning('VerifyTemporaryOrderDelayJob: TemporaryOrder not found', ['id' => $this->temporaryOrderId]);

            return;
        }

        // If the order is still waiting for verification, escalate it
        if ($temporaryOrder->status === 'waiting_verification') {
            $temporaryOrder->update([
                'status' => 'escalated',
            ]);

            // Ghi audit log hệ thống
            AuditLog::create([
                'restaurant_id' => $temporaryOrder->restaurant_id,
                'branch_id' => $temporaryOrder->branch_id,
                'user_id' => null,
                'user_role' => 'system',
                'event' => 'updated',
                'action' => 'temporary_order_escalated',
                'subject_type' => get_class($temporaryOrder),
                'subject_id' => $temporaryOrder->id,
                'old_values' => ['status' => 'waiting_verification'],
                'new_values' => ['status' => 'escalated'],
                'ip_address' => null,
                'user_agent' => 'System Scheduler / Delay Job',
            ]);

            // Phát tín hiệu Realtime cấp cứu cho Manager
            event(new TemporaryOrderEscalated($temporaryOrder));

            Log::info('VerifyTemporaryOrderDelayJob: TemporaryOrder escalated successfully', ['id' => $this->temporaryOrderId]);
        }
    }
}
