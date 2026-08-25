<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\TenantStorageSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantStorageUsageService
{
    /**
     * Capture one daily usage snapshot for every tenant, or one tenant when
     * $restaurantId is supplied. Database bytes are estimates based on row
     * counts; physical database size is reported separately at platform level.
     */
    public function captureSnapshots(?int $restaurantId = null): array
    {
        $today = now()->toDateString();
        $restaurantQuery = Restaurant::query()->orderBy('id');

        if ($restaurantId !== null) {
            $restaurantQuery->whereKey($restaurantId);
        }

        $restaurants = $restaurantQuery->get(['id']);
        if ($restaurants->isEmpty()) {
            return ['tenants' => 0, 'total_bytes' => 0, 'top_tenant_id' => null];
        }

        $tenantIds = $restaurants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stats = $this->collectTenantTableStats($tenantIds);
        $previous = TenantStorageSnapshot::query()
            ->whereIn('restaurant_id', $tenantIds)
            ->where('snapshot_date', '<', $today)
            ->orderByDesc('snapshot_date')
            ->get()
            ->groupBy('restaurant_id')
            ->map(fn ($rows) => $rows->first());

        $rows = [];
        foreach ($tenantIds as $tenantId) {
            $tenantStats = $stats[$tenantId] ?? [];
            $mediaBytes = (int) ($tenantStats['media_assets']['media_bytes'] ?? 0);
            $mediaFiles = (int) ($tenantStats['media_assets']['row_count'] ?? 0);
            $databaseRows = 0;
            $databaseBytes = 0;

            foreach ($tenantStats as $table => $tableStats) {
                $rowCount = (int) ($tableStats['row_count'] ?? 0);
                $databaseRows += $rowCount;
                $databaseBytes += $rowCount * (int) config("data_lifecycle.storage.tenant_row_bytes.{$table}", 256);
            }

            $totalBytes = $databaseBytes + $mediaBytes;
            $previousBytes = (int) ($previous[$tenantId]?->total_bytes ?? 0);

            $rows[] = [
                'restaurant_id' => $tenantId,
                'snapshot_date' => $today,
                'snapshot_at' => now(),
                'media_bytes' => $mediaBytes,
                'media_files' => $mediaFiles,
                'database_rows' => $databaseRows,
                'database_bytes' => $databaseBytes,
                'total_bytes' => $totalBytes,
                'growth_bytes' => $totalBytes - $previousBytes,
                'table_stats' => json_encode($tenantStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        TenantStorageSnapshot::query()->upsert(
            $rows,
            ['restaurant_id', 'snapshot_date'],
            [
                'snapshot_at',
                'media_bytes',
                'media_files',
                'database_rows',
                'database_bytes',
                'total_bytes',
                'growth_bytes',
                'table_stats',
                'updated_at',
            ],
        );

        $top = collect($rows)->sortByDesc('total_bytes')->first();

        return [
            'tenants' => count($rows),
            'total_bytes' => (int) collect($rows)->sum('total_bytes'),
            'top_tenant_id' => $top['restaurant_id'] ?? null,
            'snapshot_date' => $today,
        ];
    }

    /**
     * Return a platform-level capacity view for the Super Admin dashboard.
     */
    public function platformSummary(): array
    {
        $latestDate = TenantStorageSnapshot::query()->max('snapshot_date');
        $latest = $latestDate
            ? TenantStorageSnapshot::query()->whereDate('snapshot_date', $latestDate)->with('restaurant:id,name,code')->get()
            : collect();

        $databaseBytes = $this->physicalDatabaseBytes();
        $fileBytes = (int) $latest->sum('media_bytes');
        $estimatedTenantBytes = (int) $latest->sum('total_bytes');
        $limitGb = config('data_lifecycle.storage.database_limit_gb');
        $databasePercent = $limitGb && $limitGb > 0
            ? round(($databaseBytes / ($limitGb * 1024 * 1024 * 1024)) * 100, 2)
            : null;

        $top = $latest->sortByDesc('total_bytes')->take(10)->values()->map(fn (TenantStorageSnapshot $snapshot) => [
            'restaurant_id' => $snapshot->restaurant_id,
            'name' => $snapshot->restaurant?->name ?? 'N/A',
            'code' => $snapshot->restaurant?->code,
            'total_bytes' => (int) $snapshot->total_bytes,
            'media_bytes' => (int) $snapshot->media_bytes,
            'database_bytes' => (int) $snapshot->database_bytes,
            'database_rows' => (int) $snapshot->database_rows,
            'growth_bytes' => (int) $snapshot->growth_bytes,
        ])->all();

        return [
            'snapshot_date' => $latestDate,
            'database_bytes' => $databaseBytes,
            'database_size_mb' => round($databaseBytes / 1024 / 1024, 2),
            'estimated_tenant_bytes' => $estimatedTenantBytes,
            'media_bytes' => $fileBytes,
            'tenant_count' => $latest->count(),
            'database_limit_gb' => $limitGb,
            'database_percent' => $databasePercent,
            'top_tenants' => $top,
            'warning_percentages' => config('data_lifecycle.storage.warning_percentages', [70, 85, 95, 100]),
        ];
    }

    public function tenantUsage(int $restaurantId): ?array
    {
        $snapshot = TenantStorageSnapshot::query()
            ->where('restaurant_id', $restaurantId)
            ->latest('snapshot_date')
            ->first();

        if (! $snapshot) {
            return null;
        }

        return [
            'restaurant_id' => $snapshot->restaurant_id,
            'snapshot_date' => $snapshot->snapshot_date?->toDateString(),
            'media_bytes' => (int) $snapshot->media_bytes,
            'media_files' => (int) $snapshot->media_files,
            'database_rows' => (int) $snapshot->database_rows,
            'database_bytes' => (int) $snapshot->database_bytes,
            'total_bytes' => (int) $snapshot->total_bytes,
            'growth_bytes' => (int) $snapshot->growth_bytes,
            'table_stats' => $snapshot->table_stats ?? [],
        ];
    }

    public function pruneSnapshots(?Carbon $before = null, bool $dryRun = true): array
    {
        $before ??= now()->subDays((int) config('data_lifecycle.storage.snapshot_retention_days', 730));
        $query = TenantStorageSnapshot::query()->where('snapshot_at', '<', $before);
        $count = (clone $query)->count();

        if (! $dryRun) {
            $query->delete();
        }

        return [
            'before' => $before->toIso8601String(),
            'count' => $count,
            'deleted' => $dryRun ? 0 : $count,
            'dry_run' => $dryRun,
        ];
    }

    private function collectTenantTableStats(array $tenantIds): array
    {
        $stats = [];
        $tables = config('data_lifecycle.tenant_tables', []);

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'restaurant_id')) {
                continue;
            }

            $select = [
                'restaurant_id',
                DB::raw('COUNT(*) as row_count'),
            ];

            if ($table === 'media_assets') {
                $select[] = DB::raw('COALESCE(SUM(size_bytes), 0) as media_bytes');
            }

            $rows = DB::table($table)
                ->select($select)
                ->whereIn('restaurant_id', $tenantIds)
                ->groupBy('restaurant_id')
                ->get();

            foreach ($rows as $row) {
                $tenantId = (int) $row->restaurant_id;
                $stats[$tenantId][$table] = [
                    'row_count' => (int) $row->row_count,
                    'media_bytes' => (int) ($row->media_bytes ?? 0),
                ];
            }
        }

        return $stats;
    }

    private function physicalDatabaseBytes(): int
    {
        try {
            $database = config('database.connections.'.config('database.default').'.database');
            $row = DB::selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes
                 FROM information_schema.TABLES WHERE table_schema = ?',
                [$database],
            );

            return (int) ($row->size_bytes ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
