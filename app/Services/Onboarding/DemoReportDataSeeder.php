<?php

namespace App\Services\Onboarding;

use App\Models\ExpenseCategory;
use App\Models\OperatingExpense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Salary;
use App\Models\TableReservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bổ sung dữ liệu mà các báo cáo mới (P&L, BI, xu hướng 6 tháng) cần nhưng
 * DemoDataSeederService gốc chưa tạo: đơn hàng trải 6 tháng, bảng lương đã
 * duyệt, chi phí vận hành theo danh mục, và vài bản ghi trình diễn Phase 2/3.
 *
 * Idempotent: mọi bản ghi tạo ra đều mang dấu 'RPT-' / marker để xóa sạch
 * trước khi seed lại, tránh nhân đôi khi chạy nhiều lần.
 */
class DemoReportDataSeeder
{
    private const ORDER_MARKER = 'RPT-';
    private const SALARY_MARKER = '[demo-reports]';
    private const EXPENSE_MARKER = '[demo-reports]';

    public function enrich(Restaurant $restaurant): array
    {
        return DB::transaction(function () use ($restaurant) {
            $this->clearPrevious($restaurant);

            $products = Product::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->get(['id', 'name', 'price', 'preparation_time_minutes']);

            if ($products->isEmpty()) {
                throw new \RuntimeException('Nhà hàng chưa có món nào — hãy chạy seed:restaurant-demo trước.');
            }

            $tables = RestaurantTable::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->get(['id']);

            $creator = User::withoutGlobalScopes()->where('restaurant_id', $restaurant->id)->first();

            [$orderCount, $monthlyRevenue] = $this->seedSixMonthOrders($restaurant, $products, $tables, $creator);
            $salaryCount = $this->seedSalaries($restaurant, $creator, $monthlyRevenue);
            $expenseCount = $this->seedOperatingExpenses($restaurant, $creator, $monthlyRevenue);
            $this->seedShowcaseRecords($restaurant);

            return [
                'orders' => $orderCount,
                'salaries' => $salaryCount,
                'expenses' => $expenseCount,
            ];
        });
    }

    private function clearPrevious(Restaurant $restaurant): void
    {
        $orderIds = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('order_number', 'like', self::ORDER_MARKER.'%')
            ->pluck('id');

        if ($orderIds->isNotEmpty()) {
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            Order::withoutGlobalScopes()->whereIn('id', $orderIds)->forceDelete();
        }

        Salary::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('notes', 'like', '%'.self::SALARY_MARKER.'%')
            ->delete();

        OperatingExpense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurant->id)
            ->where('description', 'like', '%'.self::EXPENSE_MARKER.'%')
            ->delete();
    }

    /**
     * Đơn hoàn thành trải 6 tháng gần nhất — doanh thu tăng dần để đường xu
     * hướng đẹp. Dùng bulk insert cho tốc độ (orders không có field mã hóa).
     */
    private function seedSixMonthOrders(Restaurant $restaurant, $products, $tables, ?User $creator): array
    {
        $paymentMethods = ['cash', 'bank_transfer', 'card', 'ewallet'];
        $channels = ['dine_in', 'takeaway', 'delivery'];
        $seq = 0;
        $monthlyRevenue = [];

        for ($m = 5; $m >= 0; $m--) {
            $monthStart = CarbonImmutable::now()->startOfMonth()->subMonths($m);
            $monthKey = $monthStart->format('Y-m');
            $monthlyRevenue[$monthKey] = 0;
            $isCurrentMonth = $m === 0;
            $daysInScope = $isCurrentMonth ? (int) CarbonImmutable::now()->day : (int) $monthStart->daysInMonth;

            // Số đơn/ngày tăng dần theo tháng để đường xu hướng đi lên
            $ordersPerDay = 12 - $m; // ~7/ngày (tháng cũ) → ~12/ngày (tháng mới)
            $ordersThisMonth = $ordersPerDay * $daysInScope;

            for ($k = 0; $k < $ordersThisMonth; $k++) {
                $day = random_int(1, max(1, $daysInScope));
                $orderDate = $monthStart->addDays($day - 1)
                    ->setTime(random_int(9, 21), random_int(0, 59));

                $picked = $products->random(min(random_int(1, 4), $products->count()));
                $subtotal = 0;
                $items = [];

                foreach ($picked as $product) {
                    $qty = random_int(1, 3);
                    $lineTotal = (float) $product->price * $qty;
                    $subtotal += $lineTotal;
                    $items[] = [
                        'restaurant_id' => $restaurant->id,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price' => (float) $product->price,
                        'line_total' => $lineTotal,
                        'status' => 'served',
                        'sent_to_kitchen_at' => $orderDate,
                        'prepared_at' => $orderDate->addMinutes((int) ($product->preparation_time_minutes ?? 10)),
                        'served_at' => $orderDate->addMinutes((int) ($product->preparation_time_minutes ?? 10) + 3),
                        'created_at' => $orderDate,
                        'updated_at' => $orderDate,
                    ];
                }

                $discount = random_int(0, 100) < 25 ? round($subtotal * 0.1) : 0;
                $channel = $channels[array_rand($channels)];
                $serviceCharge = $channel === 'delivery' ? 15000 : 0;
                $total = $subtotal - $discount + $serviceCharge;

                $orderId = DB::table('orders')->insertGetId([
                    'restaurant_id' => $restaurant->id,
                    'table_id' => $channel === 'dine_in' && $tables->isNotEmpty() ? $tables->random()->id : null,
                    'order_number' => self::ORDER_MARKER.str_pad((string) (++$seq), 6, '0', STR_PAD_LEFT),
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'channel' => $channel,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'service_charge' => $serviceCharge,
                    'total_amount' => $total,
                    'created_by' => $creator?->id,
                    'cashier_user_id' => $creator?->id,
                    'confirmed_at' => $orderDate->addMinutes(2),
                    'completed_at' => $orderDate->addMinutes(35),
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                foreach ($items as &$item) {
                    $item['order_id'] = $orderId;
                }
                unset($item);
                DB::table('order_items')->insert($items);

                DB::table('payments')->insert([
                    'restaurant_id' => $restaurant->id,
                    'order_id' => $orderId,
                    'processed_by' => $creator?->id,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'status' => 'paid',
                    'amount' => $total,
                    'cash_received' => $total,
                    'change_amount' => 0,
                    'paid_at' => $orderDate->addMinutes(36),
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                $monthlyRevenue[$monthKey] += $total;
            }
        }

        return [$seq, $monthlyRevenue];
    }

    /** Doanh thu trung bình của các tháng ĐÃ TRỌN VẸN (bỏ tháng hiện tại dở dang). */
    private function averageFullMonthRevenue(array $monthlyRevenue): float
    {
        $currentKey = CarbonImmutable::now()->format('Y-m');
        $full = array_filter(
            $monthlyRevenue,
            fn ($rev, $key) => $key !== $currentKey && $rev > 0,
            ARRAY_FILTER_USE_BOTH
        );

        return $full ? array_sum($full) / count($full) : (array_sum($monthlyRevenue) ?: 1);
    }

    /**
     * Bảng lương đã duyệt cho 3 tháng đã trọn vẹn (không seed tháng hiện tại —
     * lương thực tế chốt cuối tháng). Tổng quỹ lương ≈ 25% doanh thu trung bình
     * để P&L hiển thị biên lợi nhuận hợp lý. Dùng Eloquent vì lương mã hóa.
     */
    private function seedSalaries(Restaurant $restaurant, ?User $approver, array $monthlyRevenue): int
    {
        $employees = DB::table('employees')->where('restaurant_id', $restaurant->id)->get(['id']);

        if ($employees->isEmpty()) {
            return 0;
        }

        // Tổng quỹ lương ≈ 22% doanh thu trung bình, chia đều — không đặt sàn
        // cao kẻo tổng lương vượt mục tiêu khi số nhân viên nhiều.
        $avgRevenue = $this->averageFullMonthRevenue($monthlyRevenue);
        $laborBudget = $avgRevenue * 0.22;
        $perEmployeeBase = max(2000000, round($laborBudget / $employees->count()));

        $count = 0;

        for ($m = 3; $m >= 1; $m--) {
            $monthStart = CarbonImmutable::now()->startOfMonth()->subMonths($m);
            $monthEnd = $monthStart->endOfMonth();

            foreach ($employees as $emp) {
                $base = (int) round($perEmployeeBase * (0.9 + mt_rand(0, 20) / 100));
                $bonus = random_int(0, 800000);
                $deduction = random_int(0, 300000);

                Salary::create([
                    'restaurant_id' => $restaurant->id,
                    'employee_id' => $emp->id,
                    'pay_period_start' => $monthStart->toDateString(),
                    'pay_period_end' => $monthEnd->toDateString(),
                    'base_salary' => $base,
                    'bonus_amount' => $bonus,
                    'deduction_amount' => $deduction,
                    'net_salary' => $base + $bonus - $deduction,
                    'status' => 'paid',
                    'approved_by' => $approver?->id,
                    'paid_at' => $monthEnd->addDays(3),
                    'notes' => 'Lương demo '.self::SALARY_MARKER,
                    'created_at' => $monthEnd,
                    'updated_at' => $monthEnd,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Chi phí vận hành tỷ lệ theo doanh thu THỰC của từng tháng (~20%), chia
     * theo trọng số danh mục. Nhờ vậy tháng hiện tại (dở dang, doanh thu thấp)
     * cũng có chi phí thấp tương ứng, không bị âm lợi nhuận giả tạo.
     */
    private function seedOperatingExpenses(Restaurant $restaurant, ?User $creator, array $monthlyRevenue): int
    {
        // Trọng số phân bổ trong tổng chi phí vận hành (~20% doanh thu)
        $categoryWeights = [
            'Thuê mặt bằng' => 0.45,
            'Điện nước' => 0.20,
            'Marketing' => 0.15,
            'Vật tư tiêu hao' => 0.12,
            'Bảo trì thiết bị' => 0.08,
        ];

        $categories = [];
        foreach (array_keys($categoryWeights) as $name) {
            $categories[$name] = ExpenseCategory::withoutGlobalScopes()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $name]
            )->id;
        }

        $count = 0;

        foreach ($monthlyRevenue as $monthKey => $revenue) {
            if ($revenue <= 0) {
                continue;
            }

            $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $monthKey.'-01')->startOfMonth();
            $opexTotal = $revenue * 0.15;

            foreach ($categoryWeights as $name => $weight) {
                $amount = (int) round($opexTotal * $weight);

                if ($amount <= 0) {
                    continue;
                }

                OperatingExpense::create([
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $categories[$name],
                    'amount' => $amount,
                    'expense_date' => $monthStart->addDays(random_int(0, 5))->toDateString(),
                    'description' => "{$name} tháng {$monthStart->format('m/Y')} ".self::EXPENSE_MARKER,
                    'created_by' => $creator?->id,
                    'created_at' => $monthStart,
                    'updated_at' => $monthStart,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vài bản ghi trình diễn Phase 2/3: 1 đặt bàn đã cọc + 1 đơn từ GrabFood.
     * Bọc try/catch — nếu thiếu điều kiện (không có cổng thanh toán...) thì bỏ
     * qua, không làm hỏng toàn bộ seed.
     */
    private function seedShowcaseRecords(Restaurant $restaurant): void
    {
        // Đặt bàn đã đặt cọc (confirmed)
        try {
            TableReservation::withoutGlobalScopes()->updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'guest_phone' => '0909000111'],
                [
                    'guest_name' => 'Khách VIP demo',
                    'reservation_date' => CarbonImmutable::now()->addDay()->toDateString(),
                    'reservation_time' => '19:00',
                    'party_size' => 6,
                    'status' => 'confirmed',
                    'source' => 'website',
                    'deposit_amount' => 200000,
                    'deposit_status' => 'paid',
                    'confirmed_at' => now(),
                    'special_requests' => 'Bàn gần cửa sổ, có bánh sinh nhật',
                ]
            );
        } catch (\Throwable $e) {
            // bỏ qua nếu schema đặt bàn chưa sẵn sàng
        }

        // Đơn GrabFood mô phỏng qua service thật
        try {
            $service = app(\App\Services\DeliveryPlatforms\DeliveryPlatformService::class);
            $payload = $service->buildSimulatedPayload($restaurant->id, 'grabfood');
            $service->ingestOrder($restaurant->id, 'grabfood', $payload);
        } catch (\Throwable $e) {
            // bỏ qua nếu không tạo được đơn nền tảng
        }
    }
}
