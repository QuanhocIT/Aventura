<?php

namespace App\Jobs;

use App\Services\InventoryAvailabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshBranchInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $restaurantId,
        public readonly int $branchId,
        public readonly bool $notify = true
    ) {}

    public function handle(InventoryAvailabilityService $service): void
    {
        try {
            $service->refreshBranch($this->restaurantId, $this->branchId, $this->notify);
        } catch (\Throwable $e) {
            Log::error("RefreshBranchInventoryJob failed for restaurant {$this->restaurantId}, branch {$this->branchId}: ".$e->getMessage());
            throw $e;
        }
    }
}
