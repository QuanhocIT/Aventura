<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dựng lại bảng promotion_usages cho các đơn đã áp mã TRƯỚC khi bảng này tồn tại.
 *
 * Cách cũ chỉ nối một chuỗi vào orders.note dạng:
 *     [Đã áp mã voucher: WELCOME20: -100.000đ]
 * Lệnh này bóc chuỗi đó ra để báo cáo hiệu quả từng chương trình có dữ liệu lịch
 * sử, thay vì chỉ tính từ thời điểm nâng cấp trở đi.
 */
class BackfillPromotionUsages extends Command
{
    protected $signature = 'promotions:backfill-usages
                            {--restaurant= : Chỉ xử lý một nhà hàng cụ thể}
                            {--dry-run : Chỉ thống kê, không ghi dữ liệu}';

    protected $description = 'Dựng lại promotion_usages từ ghi chú đơn hàng của các lượt áp mã cũ';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $restaurantId = $this->option('restaurant');

        // Bắt: [Đã áp mã voucher: CODE: -1.234.567đ]
        $pattern = '/\[Đã áp mã voucher:\s*([A-Z0-9_-]+):\s*-([\d.,]+)đ\]/u';

        $orders = Order::withoutGlobalScopes()
            ->when($restaurantId, fn ($q) => $q->where('restaurant_id', $restaurantId))
            ->whereNotNull('note')
            ->where('note', 'like', '%Đã áp mã voucher:%')
            ->get(['id', 'restaurant_id', 'branch_id', 'customer_id', 'note', 'subtotal', 'created_at']);

        if ($orders->isEmpty()) {
            $this->info('Không tìm thấy đơn hàng nào có ghi chú áp mã voucher.');

            return self::SUCCESS;
        }

        $this->info("Tìm thấy {$orders->count()} đơn hàng có ghi chú áp mã.");

        $promotionsByRestaurant = Promotion::withoutGlobalScopes()
            ->whereNotNull('code')
            ->when($restaurantId, fn ($q) => $q->where('restaurant_id', $restaurantId))
            ->get(['id', 'restaurant_id', 'code'])
            ->groupBy('restaurant_id')
            ->map(fn ($group) => $group->keyBy('code'));

        $created = 0;
        $skippedExisting = 0;
        $skippedUnknownCode = 0;

        foreach ($orders as $order) {
            if (! preg_match_all($pattern, (string) $order->note, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $code = strtoupper($match[1]);
                $discount = (float) str_replace(['.', ','], '', $match[2]);

                $promotion = $promotionsByRestaurant[$order->restaurant_id][$code] ?? null;

                if (! $promotion) {
                    $skippedUnknownCode++;

                    continue;
                }

                $exists = PromotionUsage::withoutGlobalScopes()
                    ->where('promotion_id', $promotion->id)
                    ->where('order_id', $order->id)
                    ->exists();

                if ($exists) {
                    $skippedExisting++;

                    continue;
                }

                if ($dryRun) {
                    $created++;

                    continue;
                }

                DB::table('promotion_usages')->insert([
                    'restaurant_id' => $order->restaurant_id,
                    'branch_id' => $order->branch_id,
                    'promotion_id' => $promotion->id,
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'applied_by' => null, // ghi chú cũ không lưu ai là người áp
                    'discount_amount' => $discount,
                    'order_subtotal' => (float) $order->subtotal,
                    'used_bypass' => false,
                    'created_at' => $order->created_at,
                    'updated_at' => now(),
                ]);

                $created++;
            }
        }

        $this->table(
            ['Đã tạo', 'Bỏ qua (đã có)', 'Bỏ qua (mã không còn tồn tại)'],
            [[$created, $skippedExisting, $skippedUnknownCode]],
        );

        if ($dryRun) {
            $this->warn('Chế độ --dry-run: chưa ghi gì vào database.');
        }

        return self::SUCCESS;
    }
}
