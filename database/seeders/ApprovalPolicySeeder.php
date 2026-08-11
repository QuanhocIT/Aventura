<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Database\Seeder;

/**
 * Nạp ma trận thẩm quyền mặc định cho mọi nhà hàng hiện có.
 *
 * Chạy lại an toàn: chỉ tạo dòng còn thiếu, không ghi đè hạn mức Chủ đã chỉnh.
 */
class ApprovalPolicySeeder extends Seeder
{
    public function run(): void
    {
        Restaurant::withoutGlobalScopes()
            ->select('id', 'name')
            ->chunkById(100, function ($restaurants): void {
                foreach ($restaurants as $restaurant) {
                    $count = ApprovalPolicyDefaults::applyTo((int) $restaurant->id);

                    if ($count > 0) {
                        $this->command?->info("  ✓ {$restaurant->name}: thêm {$count} chính sách");
                    }
                }
            });
    }
}
