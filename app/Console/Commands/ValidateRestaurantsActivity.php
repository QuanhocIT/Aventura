<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\RestaurantDailyCheck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Mail\SuperAdminValidatorReportMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class ValidateRestaurantsActivity extends Command
{
    protected $signature = 'restaurants:validate-activity {--date= : The date to validate (default is today, format YYYY-MM-DD)}';

    protected $description = 'Kiểm tra hoạt động hàng ngày của các doanh nghiệp (đơn hàng, bếp, doanh thu), gắn cờ cảnh báo và báo cáo.';

    public function handle(): int
    {
        $dateStr = $this->option('date') ?: today()->toDateString();
        $date = Carbon::parse($dateStr);
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $inactiveDaysLimit = (int) SystemSetting::get('inactive_flag_days', 7);

        $this->info("Bắt đầu kiểm định hoạt động cho ngày: {$date->toDateString()}");
        $this->info("Số ngày giới hạn không hoạt động: {$inactiveDaysLimit} ngày");

        $totalChecked = 0;
        $activeToday = 0;
        $inactiveToday = 0;
        $newlyFlaggedList = [];
        $activeTodayDetails = [];
        $inactiveTodayDetails = [];

        // Lấy danh sách nhà hàng có trạng thái active hoặc suspended
        $restaurants = Restaurant::with(['plan', 'owner', 'activeSubscription'])
            ->whereIn('status', ['active', 'suspended'])
            ->get();

        // 1. Nhóm số lượng đơn hàng theo nhà hàng
        $ordersCountByRestaurant = Order::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('restaurant_id', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('restaurant_id')
            ->pluck('count', 'restaurant_id')
            ->toArray();

        // 2. Nhóm số món ăn được chuẩn bị/phục vụ theo nhà hàng
        $dishesCountByRestaurant = OrderItem::where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('prepared_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('served_at', [$startOfDay, $endOfDay]);
            })
            ->select('restaurant_id', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('restaurant_id')
            ->pluck('count', 'restaurant_id')
            ->toArray();

        // 3. Nhóm doanh thu thực nhận theo nhà hàng
        $revenueByRestaurant = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfDay, $endOfDay])
            ->select('restaurant_id', \Illuminate\Support\Facades\DB::raw('sum(amount) as total'))
            ->groupBy('restaurant_id')
            ->pluck('total', 'restaurant_id')
            ->toArray();

        foreach ($restaurants as $restaurant) {
            $totalChecked++;

            // 1. Kiểm tra đơn hàng mới
            $ordersCount = $ordersCountByRestaurant[$restaurant->id] ?? 0;

            // 2. Kiểm tra bếp ra món
            $dishesPreparedCount = $dishesCountByRestaurant[$restaurant->id] ?? 0;

            // 3. Kiểm tra doanh thu
            $revenue = (float) ($revenueByRestaurant[$restaurant->id] ?? 0.0);

            $hadActivity = ($ordersCount > 0 || $dishesPreparedCount > 0 || $revenue > 0);

            $previousFlagged = (bool) $restaurant->is_inactive_flagged;
            $activeSubscription = $restaurant->activeSubscription;
            $hasActiveSubscription = !is_null($activeSubscription) && $activeSubscription->isActive();

            if ($hadActivity) {
                $activeToday++;
                $restaurant->last_active_at = now();
                $restaurant->is_inactive_flagged = false;
                $restaurant->inactive_flagged_at = null;
                $restaurant->save();

                $activeTodayDetails[] = [
                    'name' => $restaurant->name,
                    'code' => $restaurant->code,
                    'plan' => $restaurant->plan?->name ?? 'Free',
                    'orders_count' => $ordersCount,
                    'dishes_count' => $dishesPreparedCount,
                    'revenue' => $revenue,
                    'currency' => $restaurant->currency,
                ];
            } else {
                $inactiveToday++;
                
                // Xác định mốc cuối hoạt động để tính số ngày không hoạt động
                $lastActive = $restaurant->last_active_at ?: $restaurant->created_at;
                $inactiveDays = (int) $lastActive->diffInDays(now());

                // Nếu không hoạt động lâu ngày và có gói dịch vụ vẫn còn hạn
                $shouldFlag = ($inactiveDays >= $inactiveDaysLimit && $hasActiveSubscription);

                if ($shouldFlag) {
                    $restaurant->is_inactive_flagged = true;
                    if (!$previousFlagged) {
                        $restaurant->inactive_flagged_at = now();
                        
                        $newlyFlaggedList[] = [
                            'name' => $restaurant->name,
                            'code' => $restaurant->code,
                            'plan' => $restaurant->plan?->name ?? 'Free',
                            'ends_at' => $restaurant->subscription_ends_at?->format('d/m/Y') ?? 'N/A',
                            'last_active_at' => $restaurant->last_active_at?->format('d/m/Y H:i'),
                            'inactive_days' => $inactiveDays,
                            'email' => $restaurant->owner?->email ?? $restaurant->email ?? 'N/A',
                            'phone' => $restaurant->owner?->phone ?? $restaurant->phone ?? 'N/A',
                        ];
                    }
                    $restaurant->save();
                }

                $inactiveTodayDetails[] = [
                    'name' => $restaurant->name,
                    'code' => $restaurant->code,
                    'plan' => $restaurant->plan?->name ?? 'Free',
                    'last_active_at' => $restaurant->last_active_at?->format('d/m/Y H:i'),
                    'is_flagged' => (bool) $restaurant->is_inactive_flagged,
                ];
            }

            // Ghi nhận lịch sử kiểm tra hằng ngày
            RestaurantDailyCheck::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'checked_date' => $date->toDateString(),
                ],
                [
                    'orders_count' => $ordersCount,
                    'dishes_prepared_count' => $dishesPreparedCount,
                    'revenue' => $revenue,
                    'had_activity' => $hadActivity,
                    'subscription_status' => $activeSubscription?->status ?? 'none',
                    'is_flagged' => (bool) $restaurant->is_inactive_flagged,
                ]
            );
        }

        // 4. Gửi báo cáo cho SuperAdmin
        $totalFlagged = Restaurant::where('is_inactive_flagged', true)->count();
        $summary = [
            'total_checked' => $totalChecked,
            'active_today' => $activeToday,
            'inactive_today' => $inactiveToday,
            'total_flagged' => $totalFlagged,
        ];

        $superAdmins = User::role(config('auth.super_admin_roles', ['super_admin']))->get();

        if ($superAdmins->isNotEmpty()) {
            foreach ($superAdmins as $admin) {
                if ($admin->email) {
                    Mail::to($admin->email)->send(new SuperAdminValidatorReportMail(
                        $date->format('d/m/Y'),
                        $summary,
                        $newlyFlaggedList,
                        $activeTodayDetails,
                        $inactiveTodayDetails,
                        $inactiveDaysLimit
                    ));
                }
            }
            $this->info("Đã gửi báo cáo cho " . $superAdmins->count() . " tài khoản SuperAdmin.");
        } else {
            $this->warn("Không tìm thấy tài khoản SuperAdmin nào nhận email.");
        }

        $this->info("Hoàn tất quy trình kiểm định hoạt động.");
        return self::SUCCESS;
    }
}
