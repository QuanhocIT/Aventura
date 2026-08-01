<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOldOrders extends Command
{
    protected $signature = 'orders:archive-old
        {--months=6 : Số tháng tuổi tối thiểu (theo completed_at/cancelled_at) để bị archive}
        {--dry-run : Chỉ đếm số bản ghi sẽ bị di chuyển, không thay đổi dữ liệu}';

    protected $description = 'Chuyển orders/order_items đã completed/cancelled quá cũ sang orders_archive/order_items_archive';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMonths($months);

        $totalOrders = 0;
        $totalItems = 0;

        DB::table('orders')
            ->whereIn('status', ['completed', 'cancelled'])
            ->where(function ($query) use ($cutoff) {
                $query->where('completed_at', '<', $cutoff)
                    ->orWhere('cancelled_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(500, function ($orders) use ($dryRun, &$totalOrders, &$totalItems) {
                $orderIds = $orders->pluck('id')->all();

                if (empty($orderIds)) {
                    return;
                }

                if ($dryRun) {
                    $totalOrders += count($orderIds);
                    $totalItems += DB::table('order_items')->whereIn('order_id', $orderIds)->count();

                    return;
                }

                DB::transaction(function () use ($orders, $orderIds, &$totalOrders, &$totalItems) {
                    $items = DB::table('order_items')->whereIn('order_id', $orderIds)->get();

                    DB::table('orders_archive')->insertOrIgnore(
                        $orders->map(fn ($order) => (array) $order)->all()
                    );

                    if ($items->isNotEmpty()) {
                        DB::table('order_items_archive')->insertOrIgnore(
                            $items->map(fn ($item) => (array) $item)->all()
                        );
                    }

                    DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                    DB::table('orders')->whereIn('id', $orderIds)->delete();

                    $totalOrders += count($orderIds);
                    $totalItems += $items->count();
                });
            });

        $verb = $dryRun ? 'sẽ được archive (dry-run)' : 'đã được archive';
        $this->info("orders:archive-old — {$totalOrders} orders và {$totalItems} order_items {$verb} (cũ hơn {$months} tháng, cutoff: {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}
