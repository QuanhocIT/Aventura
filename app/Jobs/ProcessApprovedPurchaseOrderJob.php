<?php

namespace App\Jobs;

use App\Events\PurchaseOrderPlaced;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessApprovedPurchaseOrderJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $purchaseOrderId) {}

    public function handle(): void
    {
        $po = PurchaseOrder::withoutGlobalScopes()
            ->with(['supplier'])
            ->find($this->purchaseOrderId);

        if ($po && $po->status === 'approved') {
            // Trigger realtime notification to supplier
            event(new PurchaseOrderPlaced($po));
        }
    }
}
