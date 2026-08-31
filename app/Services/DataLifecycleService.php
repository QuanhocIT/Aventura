<?php

namespace App\Services;

use App\Models\DataCleanupRun;
use App\Models\Restaurant;
use App\Models\SystemSetting;
use App\Support\Partitioning\PartitionHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataLifecycleService
{
    public function __construct(
        protected MediaCleanupService $mediaCleanup,
        protected BackupRetentionService $backupRetention,
        protected TenantStorageUsageService $storageUsage,
        protected DatabaseMaintenanceService $maintenance,
        protected PartitionHelper $partitionHelper,
    ) {}

    public function preview(string $action = 'all', ?int $restaurantId = null): array
    {
        $actions = $action === 'all'
            ? ['technical', 'audit', 'media', 'backups', 'snapshots', 'partitions']
            : [$action];
        $result = [];

        foreach ($actions as $item) {
            $result[$item] = match ($item) {
                'technical' => $this->technicalPreview(),
                'audit' => $this->auditPreview(),
                'media' => $this->mediaCleanup->preview($restaurantId),
                'backups' => $this->backupRetention->preview(),
                'snapshots' => $this->storageUsage->pruneSnapshots(dryRun: true),
                'partitions' => $this->partitionPreview(),
                'orders-purge' => $this->ordersPurgePreview(),
                default => ['error' => "Unknown lifecycle action: {$item}"],
            };
        }

        return [
            'action' => $action,
            'generated_at' => now()->toIso8601String(),
            'results' => $result,
        ];
    }

    public function execute(DataCleanupRun $run): array
    {
        $parameters = $run->parameters ?? [];
        $action = (string) ($parameters['action'] ?? $run->action);
        $restaurantId = isset($parameters['restaurant_id']) ? (int) $parameters['restaurant_id'] : null;
        $actions = $action === 'all'
            ? ['technical', 'audit', 'media', 'backups', 'snapshots', 'partitions']
            : [$action];
        $result = [];

        foreach ($actions as $item) {
            $result[$item] = match ($item) {
                'technical' => $this->maintenance->optimize(['cleanup_queues', 'clear_sessions', 'cleanup_temporary'], null),
                'audit' => $this->maintenance->optimize(['archive_audit_logs'], null),
                'media' => $this->mediaCleanup->cleanup($restaurantId, false),
                'backups' => $this->backupRetention->prune(false),
                'snapshots' => $this->storageUsage->pruneSnapshots(dryRun: false),
                'partitions' => $this->prunePartitions(),
                'orders-purge' => $this->purgeOrders($run->id),
                default => ['error' => "Unknown lifecycle action: {$item}"],
            };
        }

        return [
            'action' => $action,
            'executed_at' => now()->toIso8601String(),
            'results' => $result,
        ];
    }

    public function platformSummary(): array
    {
        $summary = $this->storageUsage->platformSummary();
        $summary['legal_hold_tenants'] = Restaurant::query()->where('data_legal_hold', true)->count();
        $summary['pending_cleanup_runs'] = DataCleanupRun::query()->where('status', 'pending')->count();
        $summary['failed_cleanup_runs'] = DataCleanupRun::query()->where('status', 'failed')->count();
        $summary['scheduler'] = $this->schedulerHealth();

        return $summary;
    }

    private function technicalPreview(): array
    {
        return [
            'failed_jobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->where('failed_at', '<', now()->subDays((int) config('data_lifecycle.technical.failed_jobs_retention_days', 30)))->count()
                : 0,
            'finished_job_batches' => Schema::hasTable('job_batches')
                ? DB::table('job_batches')
                    ->where('created_at', '<', now()->subDays((int) config('data_lifecycle.technical.job_batches_retention_days', 90))->timestamp)
                    ->where(function ($q) {
                        $q->whereNotNull('finished_at')->orWhereNotNull('cancelled_at');
                    })->count()
                : 0,
            'expired_sessions' => Schema::hasTable('sessions')
                ? DB::table('sessions')->where('last_activity', '<', time() - config('session.lifetime', 120) * 60)->count()
                : 0,
        ];
    }

    private function auditPreview(): array
    {
        $retentionMonths = (int) SystemSetting::get(
            'audit_retention_months',
            config('data_lifecycle.audit.archive_months', 6),
        );
        $cutoff = now()->subMonths(max(1, $retentionMonths));

        return [
            'cutoff' => $cutoff->toIso8601String(),
            'eligible_count' => Schema::hasTable('audit_logs')
                ? DB::table('audit_logs')->where('created_at', '<', $cutoff)->count()
                : 0,
        ];
    }

    private function partitionPreview(): array
    {
        $tables = config('partitioning.tables', []);
        $result = [];

        foreach ($tables as $table => $policy) {
            if ($policy['retention_months'] === null) {
                continue;
            }

            $retention = $table === 'audit_logs'
                ? (int) SystemSetting::get('audit_retention_months', $policy['retention_months'])
                : $policy['retention_months'];

            $result[$table] = [
                'retention_months' => $retention,
                'eligible_partitions' => $this->partitionHelper->oldPartitions($table, $retention),
                'dry_run' => true,
            ];
        }

        return $result;
    }

    private function prunePartitions(): array
    {
        Artisan::call('db:manage-partitions', ['--prune' => true]);

        return [
            'status' => 'success',
            'output' => Artisan::output(),
        ];
    }

    private function ordersPurgePreview(): array
    {
        $months = (int) config('data_lifecycle.orders.purge_months', 36);
        $tables = ['orders_archive', 'order_items_archive', 'order_related_archives'];
        $eligible = [];

        foreach ($tables as $table) {
            $eligible[$table] = Schema::hasTable($table)
                ? $this->partitionHelper->oldPartitions($table, $months)
                : [];
        }

        return [
            'retention_months' => $months,
            'eligible_partitions' => $eligible,
            'dry_run' => true,
        ];
    }

    private function purgeOrders(int $runId): array
    {
        $exitCode = Artisan::call('orders:purge', [
            '--months' => (int) config('data_lifecycle.orders.purge_months', 36),
            '--confirm' => true,
            '--approval-run' => $runId,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(Artisan::output() ?: 'orders:purge failed.');
        }

        return [
            'status' => Artisan::output() !== '' ? 'success' : 'completed',
            'output' => Artisan::output(),
        ];
    }

    private function schedulerHealth(): array
    {
        $heartbeatPath = storage_path('framework/scheduler-heartbeat.json');
        $lastRunAt = null;

        if (is_file($heartbeatPath)) {
            $payload = json_decode((string) file_get_contents($heartbeatPath), true);
            $lastRunAt = $payload['last_run_at'] ?? null;
        }

        $minutesSinceRun = $lastRunAt ? now()->diffInMinutes($lastRunAt) : null;
        $maxMinutes = (int) env('SCHEDULER_HEARTBEAT_MAX_MINUTES', 10);

        return [
            'last_run_at' => $lastRunAt,
            'minutes_since_run' => $minutesSinceRun,
            'max_minutes' => $maxMinutes,
            'healthy' => $minutesSinceRun !== null && $minutesSinceRun <= $maxMinutes,
        ];
    }
}
