<?php

namespace App\Console\Commands;

use App\Services\AutoCheckoutService;
use Illuminate\Console\Command;

class AutoCheckoutExpiredAttendance extends Command
{
    protected $signature = 'attendance:auto-checkout';

    protected $description = 'Tự động check-out nhân viên đã hết ca nhưng chưa bấm check-out';

    public function handle(AutoCheckoutService $autoCheckout): int
    {
        $closedCount = $autoCheckout->closeExpiredAssignments();
        $this->info("Đã tự động check-out {$closedCount} ca hết giờ.");

        return self::SUCCESS;
    }
}
