<?php

namespace App\Console\Commands;

use App\Models\SystemAlert;
use App\Services\DataLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckDataLifecycleHealth extends Command
{
    protected $signature = 'data:health-check';

    protected $description = 'Check scheduler heartbeat and data capacity health';

    public function handle(DataLifecycleService $lifecycle): int
    {
        $summary = $lifecycle->platformSummary();
        $scheduler = $summary['scheduler'];
        $issues = [];

        if (! $scheduler['healthy']) {
            $issues[] = 'scheduler heartbeat is stale';
        }

        if (($summary['database_percent'] ?? 0) >= 85) {
            $issues[] = 'database capacity is above 85%';
        }

        if (! empty($issues)) {
            $this->warn(implode('; ', $issues));
            $this->openAlert($summary, $issues);

            return self::FAILURE;
        }

        $this->info('Data lifecycle health is healthy.');

        return self::SUCCESS;
    }

    private function openAlert(array $summary, array $issues): void
    {
        if (! Schema::hasTable('system_alerts')) {
            return;
        }

        $metricKey = 'platform.data_lifecycle_health';
        if (SystemAlert::query()->where('metric_key', $metricKey)->where('status', 'open')->exists()) {
            return;
        }

        SystemAlert::create([
            'metric_key' => $metricKey,
            'status' => 'open',
            'metric_value' => count($issues),
            'threshold' => 0,
            'title' => 'Data lifecycle cần kiểm tra',
            'message' => implode('; ', $issues),
            'channels' => ['dashboard', 'webhook'],
            'meta' => ['summary' => $summary],
            'triggered_at' => now(),
        ]);
    }
}
