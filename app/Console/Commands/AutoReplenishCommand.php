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
            $restaurants = Restaurant::where('id', $restaurantId)->with('users')->get();
        } else {
            $restaurants = Restaurant::where('status', 'active')->with('users')->get();
        }

        if ($restaurants->isEmpty()) {
            $this->warn('Không có nhà hàng hoạt động nào được tìm thấy.');
            return 0;
        }

        foreach ($restaurants as $restaurant) {
            $this->info("Bắt đầu xử lý dự báo cho nhà hàng: {$restaurant->name} (ID: {$restaurant->id})");

            // Find an owner or manager to associate as the PO creator (defaulting to the first owner/system user)
            $owner = $restaurant->users->where('status', 'active')->first();

            if (!$owner) {
                $this->warn("Bỏ qua nhà hàng {$restaurant->name}: Không tìm thấy tài khoản quản trị hoạt động.");
                continue;
            }

            try {
                $forecasts = [];
                // Forecasts and replenishment orders are generated per branch.
                $this->info("Đã nhận dự báo cho " . count($forecasts) . " nguyên vật liệu.");

                // Generate replenishment orders (Draft POs) per branch.
                $pos = [];
                foreach ($restaurant->branches()->where('status', 'active')->get(['id', 'name']) as $branch) {
                    $branchForecasts = $replenishService->getForecastAndReplenish($restaurant->id, $branch->id);
                    $this->info("Forecast branch {$branch->name}: ".count($branchForecasts)." ingredients.");
                    $pos = array_merge(
                        $pos,
                        $replenishService->generateReplenishmentOrders($restaurant->id, $branchForecasts, $owner->id, $branch->id),
                    );
                }

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
