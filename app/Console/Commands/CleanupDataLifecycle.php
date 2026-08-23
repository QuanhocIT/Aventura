<?php

namespace App\Console\Commands;

use App\Models\DataCleanupRun;
use App\Services\DataLifecycleService;
use Illuminate\Console\Command;

class CleanupDataLifecycle extends Command
{
    protected $signature = 'data:cleanup
        {--action=all : technical, audit, media, backups, snapshots, partitions, orders-purge, or all}
        {--tenant= : Restrict media cleanup to one restaurant ID}
        {--confirm : Execute the cleanup instead of producing a dry-run}
        {--run= : Execute an approved data_cleanup_runs ID}
        {--automatic : Allow only configured non-interactive maintenance actions}';

    protected $description = 'Preview or execute approval-controlled data lifecycle maintenance';

    public function handle(DataLifecycleService $lifecycle): int
    {
        $action = (string) $this->option('action');
        $allowed = ['technical', 'audit', 'media', 'backups', 'snapshots', 'partitions', 'orders-purge', 'all'];

        if (! in_array($action, $allowed, true)) {
            $this->error('Invalid action. Allowed: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        if (! $this->option('confirm')) {
            $this->line(json_encode($lifecycle->preview($action, $tenantId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $automatic = (bool) $this->option('automatic');
        $runId = $this->option('run');

        if (! $runId && config('data_lifecycle.require_approval', true) && ! $automatic) {
            $this->error('Production cleanup requires an approved run ID. Generate a dry-run in the Data Lifecycle screen first.');

            return self::FAILURE;
        }

        if ($automatic && ! in_array($action, ['technical', 'audit', 'snapshots', 'backups'], true)) {
            $this->error('Automatic mode only allows technical, audit, snapshots, or backups cleanup.');

            return self::FAILURE;
        }

        $run = $runId
            ? DataCleanupRun::query()->find((int) $runId)
            : DataCleanupRun::query()->create([
                'action' => $action,
                'status' => 'pending',
                'dry_run' => false,
                'approval_required' => false,
                'parameters' => [
                    'action' => $action,
                    'restaurant_id' => $tenantId,
                    'automatic' => true,
                ],
                'requested_at' => now(),
            ]);

        if (! $run) {
            $this->error('Cleanup run not found.');

            return self::FAILURE;
        }

        if ($run->status !== 'pending') {
            $this->error("Cleanup run #{$run->id} is not pending (status: {$run->status}).");

            return self::FAILURE;
        }

        $run->forceFill([
            'status' => 'running',
            'dry_run' => false,
            'started_at' => now(),
            'approved_at' => $run->approved_at ?? now(),
        ])->save();

        try {
            $result = $lifecycle->execute($run);
            $run->forceFill([
                'status' => 'success',
                'result' => $result,
                'finished_at' => now(),
            ])->save();
            $this->info("Cleanup run #{$run->id} completed successfully.");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();
            $this->error('Cleanup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
