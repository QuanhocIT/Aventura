<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireOutdatedPromotions extends Command
{
    protected $signature = 'promotions:expire-outdated';

    protected $description = 'Deactivate promotions that have passed their end date';

    public function handle()
    {
        $this->info('Starting checking for outdated promotions...');

        // withoutGlobalScopes: lệnh chạy cho MỌI nhà hàng. Nếu một ngày nào đó
        // lệnh được gọi từ trong request/queue có tenant context, global scope
        // 'restaurant' sẽ âm thầm thu hẹp phạm vi xuống một nhà hàng duy nhất.
        // (CheckPromotionBudgets đã làm đúng, chỗ này thì chưa.)
        $expiredCount = Promotion::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', Carbon::now())
            ->update(['is_active' => false]);

        $this->info("Completed. Deactivated {$expiredCount} outdated promotions.");

        return Command::SUCCESS;
    }
}
