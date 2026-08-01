<?php

namespace App\Console\Commands;

use App\Services\LoyaltyService;
use Illuminate\Console\Command;

class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire loyalty points that have passed their expiry date (FIFO)';

    public function handle(LoyaltyService $loyalty): int
    {
        $expired = $loyalty->expirePoints();

        $this->info("Đã hết hạn {$expired} điểm loyalty.");

        return self::SUCCESS;
    }
}
