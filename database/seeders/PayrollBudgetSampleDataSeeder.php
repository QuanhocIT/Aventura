<?php

namespace Database\Seeders;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Models\WageTier;
use App\Services\PayrollBudgetService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollBudgetSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'enterprise@test.com')->first();
        if (! $user) {
            $this->command->error('User enterprise@test.com not found!');
            return;
        }

        $restaurantId = $user->restaurant_id;
        $this->command->info("Seeding payroll budget & wage tiers for Restaurant ID: {$restaurantId}");

        // 1. Tạo các bậc lương mẫu thực tế ngành F&B
        $tiersData = [
            [
                'name' => 'Quản lý nhà hàng (Store Manager)',
                'compensation_type' => 'fixed',
                'rate' => 18000000,
                'revenue_percent' => 1.5,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Bếp trưởng (Head Chef)',
                'compensation_type' => 'fixed',
                'rate' => 16000000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Giám sát ca (Shift Supervisor)',
                'compensation_type' => 'fixed',
                'rate' => 9500000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Bếp chính (Line Cook)',
                'compensation_type' => 'fixed',
                'rate' => 8500000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Pha chế chính (Head Bartender)',
                'compensation_type' => 'fixed',
                'rate' => 8000000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Thu ngân kiêm Order',
                'compensation_type' => 'hourly',
                'rate' => 32000,
                'revenue_percent' => 0.5,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Nhân viên Phục vụ Full-time',
                'compensation_type' => 'fixed',
                'rate' => 6500000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Phục vụ Part-time (Theo ca)',
                'compensation_type' => 'shift',
                'rate' => 250000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Phụ bếp / Tiếp thực sinh viên',
                'compensation_type' => 'hourly',
                'rate' => 28000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Tạp vụ & Rửa bát',
                'compensation_type' => 'fixed',
                'rate' => 6000000,
                'revenue_percent' => null,
                'branch_id' => null,
                'is_active' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($tiersData as $tier) {
            WageTier::updateOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'name' => $tier['name'],
                ],
                [
                    'branch_id' => $tier['branch_id'],
                    'compensation_type' => $tier['compensation_type'],
                    'rate' => $tier['rate'],
                    'revenue_percent' => $tier['revenue_percent'],
                    'is_active' => $tier['is_active'],
                    'sort_order' => $tier['sort_order'],
                ]
            );
        }

        $this->command->info('Created/Updated ' . count($tiersData) . ' wage tiers.');

        // 2. Thiết lập Quỹ lương tháng cho từng chi nhánh
        $budgetService = app(PayrollBudgetService::class);
        $month = Carbon::now()->startOfMonth();
        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)->get();

        $defaultBudgets = [
            'Chi nhánh Quận 1' => 85000000,
            'Chi nhánh Quận 3' => 65000000,
            'Chi nhánh Quận 7' => 55000000,
            'Chi nhánh Thủ Đức' => 50000000,
        ];

        $fallbackBudgets = [80000000, 65000000, 55000000, 45000000, 40000000];

        foreach ($branches as $index => $branch) {
            $committed = $budgetService->committedMonthlyWages($restaurantId, $branch->id);

            // Tìm mức ngân sách phù hợp: hoặc theo tên, hoặc lớn hơn committed 15%, tối thiểu 40tr
            $suggested = $defaultBudgets[$branch->name] ?? ($fallbackBudgets[$index % count($fallbackBudgets)] ?? 50000000);
            $budgetAmount = max($suggested, (float) $committed * 1.15);
            $budgetAmount = round($budgetAmount / 1000000) * 1000000; // Tròn triệu

            BranchPayrollBudget::updateOrCreate(
                [
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branch->id,
                    'effective_month' => $month->toDateString(),
                ],
                [
                    'budget_amount' => $budgetAmount,
                    'notes' => "Hạn mức quỹ lương tháng {$month->format('m/Y')} đã duyệt (gồm 10-15% dự phòng OT).",
                    'created_by' => $user->id,
                ]
            );

            $this->command->info("Set budget for {$branch->name}: " . number_format($budgetAmount) . " VND (Committed: " . number_format($committed) . " VND)");
        }
    }
}
