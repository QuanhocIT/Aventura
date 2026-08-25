<?php

namespace App\Console\Commands;

use App\Services\TrainingService;
use Illuminate\Console\Command;

class SyncTrainingCompliance extends Command
{
    protected $signature = 'training:sync-compliance';

    protected $description = 'Đánh dấu đào tạo quá hạn và cập nhật trạng thái tuân thủ';

    public function handle(TrainingService $trainingService): int
    {
        $count = $trainingService->syncDueStatuses();
        $this->info("Đã cập nhật {$count} đăng ký đào tạo quá hạn.");

        return self::SUCCESS;
    }
}
