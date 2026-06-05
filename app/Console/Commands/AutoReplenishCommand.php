<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\InventoryReplenishService;
use Illuminate\Console\Command;

class AutoReplenishCommand extends Command
{
    protected $signature = 'aventura:auto-replenish {--restaurant= : The ID of the specific restaurant to run for}';

    protected $description = 'Chạy dự báo tồn kho nguyên liệu AI và tự động đề xuất đơn đặt hàng PO nháp.';

    public function handle(InventoryReplenishService $replenishService): int
    {
        $restaurantId = $this->option('restaurant');

        if ($restaurantId) {
            $restaurants = Restaurant::where('id', $restaurantId)->get();
        } else {
            $restaurants = Restaurant::where('status', 'active')->get();
        }

        if ($restaurants->isEmpty()) {
            $this->warn('Không có nhà hàng hoạt động nào được tìm thấy.');
            return 0;
        }

        foreach ($restaurants as $restaurant) {
            $this->info("Bắt đầu xử lý dự báo cho nhà hàng: {$restaurant->name} (ID: {$restaurant->id})");

            // Find an owner or manager to associate as the PO creator (defaulting to the first owner/system user)
            $owner = User::where('restaurant_id', $restaurant->id)
                ->where('status', 'active')
                ->first();

            if (!$owner) {
                $this->warn("Bỏ qua nhà hàng {$restaurant->name}: Không tìm thấy tài khoản quản trị hoạt động.");
                continue;
            }

            try {
                // 1. Get forecasts
                $forecasts = $replenishService->getForecastAndReplenish($restaurant->id);
                $this->info("Đã nhận dự báo cho " . count($forecasts) . " nguyên vật liệu.");

                // 2. Generate replenishment orders (Draft POs)
                $pos = $replenishService->generateReplenishmentOrders($restaurant->id, $forecasts, $owner->id);

                if (empty($pos)) {
                    $this->info("Tồn kho ở mức an toàn. Không có đơn đặt hàng đề xuất nào được tạo.");
                } else {
                    $this->info("Thành công: Đã tạo " . count($pos) . " đơn đặt hàng PO nháp mới:");
                    foreach ($pos as $po) {
                        $this->line("  - Đơn PO: {$po->po_number} | Đối tác: {$po->supplier->name} | Tổng tiền: " . number_format($po->total_amount) . "đ");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Lỗi khi chạy dự báo cho nhà hàng {$restaurant->id}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
