<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Redesign 4 gói dịch vụ: Free → Starter → Pro → Enterprise
        // Chuẩn hoá features JSON với 13 flags nhất quán
        $plans = [
            [
                'code' => 'free',
                'name' => 'Miễn Phí',
                'price' => 0,
                'max_branches' => 1,
                'max_tables' => 15,
                'max_users' => 5,
                'features' => [
                    'kitchen_display' => false,
                    'qr_ordering' => false,
                    'inventory_basic' => false,
                    'hr_timekeeping' => false,
                    'hr_full' => false,
                    'advanced_analytics' => false,
                    'realtime' => false,
                    'fraud_detection' => false,
                    'email_reports' => false,
                    'ai_advisor' => false,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 2,
                    'max_storage_mb' => 500,
                    'api_rate_limit' => 30,
                ],
            ],
            [
                'code' => 'starter',
                'name' => 'Cơ Bản',
                'price' => 299000,
                'max_branches' => 3,
                'max_tables' => 60,
                'max_users' => 20,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => false,
                    'advanced_analytics' => false,
                    'realtime' => true,
                    'fraud_detection' => false,
                    'email_reports' => false,
                    'ai_advisor' => false,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 8,
                    'max_storage_mb' => 5120,
                    'api_rate_limit' => 120,
                ],
            ],
            [
                'code' => 'pro',
                'name' => 'Chuyên Nghiệp',
                'price' => 699000,
                'max_branches' => 10,
                'max_tables' => 200,
                'max_users' => 60,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'advanced_analytics' => true,
                    'realtime' => true,
                    'fraud_detection' => true,
                    'email_reports' => true,
                    'ai_advisor' => true,
                    'supplier_portal' => false,
                    'ai_forecasting' => false,
                    'api_access' => false,
                    'max_areas' => 25,
                    'max_storage_mb' => 51200,
                    'api_rate_limit' => 600,
                ],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Doanh Nghiệp',
                'price' => 1499000,
                'max_branches' => null,
                'max_tables' => null,
                'max_users' => null,
                'features' => [
                    'kitchen_display' => true,
                    'qr_ordering' => true,
                    'inventory_basic' => true,
                    'hr_timekeeping' => true,
                    'hr_full' => true,
                    'advanced_analytics' => true,
                    'realtime' => true,
                    'fraud_detection' => true,
                    'email_reports' => true,
                    'ai_advisor' => true,
                    'supplier_portal' => true,
                    'ai_forecasting' => true,
                    'api_access' => true,
                    'max_areas' => null,
                    'max_storage_mb' => 204800,
                    'api_rate_limit' => 3000,
                ],
            ],
        ];

        // Map old codes → new codes để giữ FK relationships
        $codeMap = [
            'pro' => 'starter',   // cũ 499k → mới 299k Cơ Bản
            'max' => 'pro',       // cũ 999k → mới 699k Chuyên Nghiệp
            'ultra' => 'enterprise', // cũ 1,999k → mới 1,499k Doanh Nghiệp
        ];

        // Đổi code cũ trước (tránh unique constraint conflict)
        foreach ($codeMap as $oldCode => $newCode) {
            DB::table('subscription_plans')
                ->where('code', $oldCode)
                ->update(['code' => '__tmp_'.$oldCode]);
        }

        // Update từng plan theo code mới
        foreach ($plans as $plan) {
            $oldCode = array_search($plan['code'], $codeMap);

            if ($oldCode) {
                // Cập nhật plan đổi code
                DB::table('subscription_plans')
                    ->where('code', '__tmp_'.$oldCode)
                    ->update([
                        'code' => $plan['code'],
                        'name' => $plan['name'],
                        'price' => $plan['price'],
                        'max_branches' => $plan['max_branches'],
                        'max_tables' => $plan['max_tables'],
                        'max_users' => $plan['max_users'],
                        'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            } else {
                // Cập nhật plan giữ nguyên code (free)
                DB::table('subscription_plans')
                    ->where('code', $plan['code'])
                    ->update([
                        'name' => $plan['name'],
                        'price' => $plan['price'],
                        'max_branches' => $plan['max_branches'],
                        'max_tables' => $plan['max_tables'],
                        'max_users' => $plan['max_users'],
                        'features' => json_encode($plan['features'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Không rollback dữ liệu business — chỉ log
    }
};
