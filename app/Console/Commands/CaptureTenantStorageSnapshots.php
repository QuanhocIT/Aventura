<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\DataLifecycleService;
use App\Services\TenantStorageUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CaptureTenantStorageSnapshots extends Command
{
    protected $signature = 'data:storage-snapshot {--tenant= : Capture only one restaurant ID}';

    protected $description = 'Capture per-tenant storage/database usage and emit capacity alerts';

    public function handle(TenantStorageUsageService $usage, DataLifecycleService $lifecycle): int
    {
        if (! config('data_lifecycle.enabled', true)) {
            $this->comment('Data lifecycle is disabled.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $result = $usage->captureSnapshots($tenantId);
        $summary = $lifecycle->platformSummary();

        $this->info("Captured {$result['tenants']} tenant storage snapshot(s).");
        $this->line('Estimated tenant bytes: '.number_format((int) $result['total_bytes']).'.');

        $this->emitCapacityAlert($summary);

        return self::SUCCESS;
    }

    private function emitCapacityAlert(array $summary): void
    {
        $percent = $summary['database_percent'];
        if ($percent === null || $percent < 85 || ! Schema::hasTable('system_alerts')) {
            return;
        }

        $metricKey = 'platform.database_capacity';
        $alreadyOpen = SystemAlert::query()
            ->where('metric_key', $metricKey)
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        SystemAlert::create([
            'metric_key' => $metricKey,
            'status' => 'open',
            'metric_value' => $percent,
            'threshold' => 85,
            'title' => 'Cảnh báo dung lượng database',
            'message' => "Database đang sử dụng khoảng {$percent}% ngưỡng cấu hình.",
            'channels' => ['dashboard', 'webhook'],
            'meta' => [
                'database_bytes' => $summary['database_bytes'],
                'database_limit_gb' => $summary['database_limit_gb'],
                'snapshot_date' => $summary['snapshot_date'],
            ],
            'triggered_at' => now(),
        ]);
    }
}
