<?php

namespace App\Console\Commands;

use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DepreciateFixedAssets extends Command
{
    protected $signature = 'finance:depreciate-assets {--month= : Kỳ YYYY-MM, mặc định kỳ hiện tại} {--restaurant= : Chỉ xử lý một nhà hàng}';

    protected $description = 'Ghi nhận khấu hao tài sản cố định theo tháng';

    public function handle(FixedAssetService $service): int
    {
        $month = (string) ($this->option('month') ?: now()->format('Y-m'));
        try {
            CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');
        } catch (\Throwable) {
            $this->error('--month phải có định dạng YYYY-MM.');
            return self::INVALID;
        }

        $query = FixedAsset::withoutGlobalScopes()->where('status', 'active');
        if ($this->option('restaurant')) {
            $query->where('restaurant_id', (int) $this->option('restaurant'));
        }

        $processed = 0;
        $failed = 0;
        $query->orderBy('id')->chunkById(100, function ($assets) use ($service, $month, &$processed, &$failed): void {
            foreach ($assets as $asset) {
                try {
                    $service->depreciate($asset, $month);
                    $processed++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->warn("Không thể khấu hao tài sản {$asset->asset_code}: {$exception->getMessage()}");
                }
            }
        });

        $this->info("Đã xử lý {$processed} tài sản; lỗi {$failed}.");
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
