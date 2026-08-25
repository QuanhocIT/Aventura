<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchiveOldOrders extends Command
{
    protected $signature = 'orders:archive-old
        {--months=6 : Số tháng tuổi tối thiểu theo completed_at/cancelled_at để archive}
        {--tenant= : Chỉ archive một restaurant ID}
        {--dry-run : Chỉ đếm bản ghi, không thay đổi dữ liệu}';

    protected $description = 'Chuyển đơn hàng đã hoàn tất/hủy lâu ngày sang archive, giữ lại dữ liệu liên quan trước khi xóa khỏi bảng nóng';

    public function handle(): int
    {
        foreach (['orders_archive', 'order_items_archive', 'order_related_archives'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Thiếu bảng {$table}. Hãy chạy migrate trước khi archive.");

                return self::FAILURE;
            }
        }

        $months = max(1, (int) $this->option('months'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMonths($months);
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $relatedTables = $this->relatedOrderTables();
        $orderArchiveColumns = Schema::getColumnListing('orders_archive');
        $itemArchiveColumns = Schema::getColumnListing('order_items_archive');

        $totalOrders = 0;
        $totalItems = 0;
        $totalRelated = 0;

        $query = DB::table('orders')
            ->whereIn('status', ['completed', 'cancelled'])
            ->where(function ($query) use ($cutoff) {
                $query->where('completed_at', '<', $cutoff)
                    ->orWhere('cancelled_at', '<', $cutoff);
            })
            ->when($tenantId !== null, fn ($query) => $query->where('restaurant_id', $tenantId));

        if (Schema::hasColumn('restaurants', 'data_legal_hold')) {
            $query->whereNotExists(function ($holdQuery) {
                $holdQuery->select(DB::raw(1))
                    ->from('restaurants')
                    ->whereColumn('restaurants.id', 'orders.restaurant_id')
                    ->where('restaurants.data_legal_hold', true);
            });
        }

        $query->orderBy('id')->chunkById(200, function ($orders) use (
            $dryRun,
            $relatedTables,
            $orderArchiveColumns,
            $itemArchiveColumns,
            &$totalOrders,
            &$totalItems,
            &$totalRelated,
        ) {
            $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (empty($orderIds)) {
                return;
            }

            $items = DB::table('order_items')->whereIn('order_id', $orderIds)->get();
            $relatedCount = 0;
            foreach ($relatedTables as $table) {
                $relatedCount += DB::table($table)->whereIn('order_id', $orderIds)->count();
            }

            if ($dryRun) {
                $totalOrders += count($orderIds);
                $totalItems += $items->count();
                $totalRelated += $relatedCount;

                return;
            }

            DB::transaction(function () use (
                $orders,
                $items,
                $orderIds,
                $relatedTables,
                $orderArchiveColumns,
                $itemArchiveColumns,
                &$totalOrders,
                &$totalItems,
                &$totalRelated,
            ) {
                DB::table('orders_archive')->insertOrIgnore(
                    $orders->map(fn ($order) => $this->archiveRow($order, $orderArchiveColumns))->all()
                );

                if ($items->isNotEmpty()) {
                    DB::table('order_items_archive')->insertOrIgnore(
                        $items->map(fn ($item) => $this->archiveRow($item, $itemArchiveColumns))->all()
                    );
                }

                $restaurantIds = $orders->pluck('restaurant_id', 'id')->map(fn ($id) => (int) $id);
                $relatedArchived = 0;

                foreach ($relatedTables as $table) {
                    $rows = DB::table($table)->whereIn('order_id', $orderIds)->get();
                    if ($rows->isEmpty()) {
                        continue;
                    }

                    $archiveRows = $rows->map(function ($row) use ($table, $restaurantIds) {
                        $source = (array) $row;
                        $payload = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $sourceId = array_key_exists('id', $source)
                            ? (string) $source['id']
                            : hash('sha256', (string) $payload);

                        return [
                            'restaurant_id' => (int) ($source['restaurant_id'] ?? $restaurantIds[(int) $source['order_id']] ?? 0),
                            'order_id' => (int) $source['order_id'],
                            'source_table' => $table,
                            'source_id' => $sourceId,
                            'payload' => $payload,
                            'created_at' => $source['created_at'] ?? now(),
                            'archived_at' => now(),
                        ];
                    })->all();

                    foreach (array_chunk($archiveRows, 500) as $chunk) {
                        DB::table('order_related_archives')->insertOrIgnore($chunk);
                    }

                    DB::table($table)->whereIn('order_id', $orderIds)->delete();
                    $relatedArchived += count($archiveRows);
                }

                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();

                $totalOrders += count($orderIds);
                $totalItems += $items->count();
                $totalRelated += $relatedArchived;
            });
        });

        $verb = $dryRun ? 'sẽ được archive (dry-run)' : 'đã được archive';
        $this->info(
            "orders:archive-old — {$totalOrders} orders, {$totalItems} order_items và {$totalRelated} bản ghi liên quan {$verb} (cũ hơn {$months} tháng, cutoff: {$cutoff->toDateString()})."
        );

        return self::SUCCESS;
    }

    private function archiveRow(object $row, array $archiveColumns): array
    {
        $source = (array) $row;
        $allowed = array_flip($archiveColumns);
        $result = array_intersect_key($source, $allowed);
        $extra = array_diff_key($source, $allowed);

        if (array_key_exists('archive_metadata', $allowed)) {
            $result['archive_metadata'] = empty($extra)
                ? null
                : json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $result;
    }

    /**
     * Find every FK child table that would otherwise be deleted by removing
     * an order. The generic archive keeps those rows recoverable in one place.
     */
    private function relatedOrderTables(): array
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $tables = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('REFERENCED_TABLE_SCHEMA', DB::getDatabaseName())
                ->where('REFERENCED_TABLE_NAME', 'orders')
                ->where('REFERENCED_COLUMN_NAME', 'id')
                ->pluck('TABLE_NAME')
                ->all();
        } else {
            $tables = [
                'payments',
                'customer_feedback',
                'delivery_details',
                'platform_orders',
                'inventory_reservations',
                'debt_settlements',
                'promotion_usages',
            ];
        }

        return collect($tables)
            ->unique()
            ->reject(fn (string $table) => in_array($table, ['orders', 'order_items'], true))
            ->filter(fn (string $table) => Schema::hasTable($table) && Schema::hasColumn($table, 'order_id'))
            ->values()
            ->all();
    }
}
