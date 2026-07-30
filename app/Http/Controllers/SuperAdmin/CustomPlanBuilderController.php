<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomPlanBuilderController extends Controller
{
    private array $featureFlags = [
        'kitchen_display', 'qr_ordering', 'inventory_basic', 'hr_timekeeping',
        'hr_full', 'advanced_analytics', 'realtime', 'fraud_detection',
        'email_reports', 'ai_advisor', 'supplier_portal', 'ai_forecasting', 'api_access',
        'rfm_ai_analysis', 'priority_support',
    ];

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'price' => 'required|integer|min:0',
            'billing_cycle' => 'required|string|max:50',
            'max_branches' => 'required|integer|min:-1',
            'max_tables' => 'required|integer|min:-1',
            'max_users' => 'required|integer|min:-1',
            'max_dishes' => 'required|integer|min:-1',
            'max_areas' => 'required|integer|min:-1',
            'max_storage_mb' => 'required|integer|min:1',
            'api_rate_limit' => 'required|integer|min:10',
            'password' => 'required|string',
            ...(array_fill_keys($this->featureFlags, 'boolean')),
        ]);

        // Xác nhận mật khẩu hiện tại của Super Admin
        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Mật khẩu xác nhận không chính xác.']);
        }

        $toNull = fn ($v) => $v === -1 ? null : $v;

        $features = [
            'description' => 'Gói dịch vụ tùy chỉnh (ad-hoc) dành riêng cho doanh nghiệp '.$restaurant->name,
            'max_areas' => $validated['max_areas'],
            'max_storage_mb' => $validated['max_storage_mb'],
            'api_rate_limit' => $validated['api_rate_limit'],
        ];
        foreach ($this->featureFlags as $flag) {
            $features[$flag] = (bool) ($validated[$flag] ?? false);
        }

        DB::transaction(function () use ($restaurant, $request, $validated, $features, $toNull) {
            // 1. Tạo Gói dịch vụ Custom (Ad-hoc)
            $planCode = 'custom_'.$restaurant->id.'_'.time();
            $planName = $validated['name'] ?: 'Gói Custom - '.$restaurant->name;

            $plan = SubscriptionPlan::create([
                'restaurant_id' => $restaurant->id,
                'is_custom' => true,
                'code' => $planCode,
                'name' => $planName,
                'price' => $validated['price'],
                'billing_cycle' => $validated['billing_cycle'],
                'max_branches' => $toNull($validated['max_branches']),
                'max_tables' => $toNull($validated['max_tables']),
                'max_users' => $toNull($validated['max_users']),
                'max_dishes' => $toNull($validated['max_dishes']),
                'status' => 'active',
                'features' => $features,
            ]);

            // 2. Tính số ngày của chu kỳ thanh toán
            $days = match ($plan->billing_cycle) {
                'quarterly' => 90,
                'half_yearly' => 180,
                'yearly' => 365,
                'biennial' => 730,
                default => 30, // monthly
            };
            $endedAt = now()->addDays($days);

            // 3. Vô hiệu hóa/đánh dấu hết hạn các subscription cũ
            $restaurant->subscriptions()->where('status', 'active')->update(['status' => 'expired']);

            // 4. Tạo Subscription mới hoạt động
            RestaurantSubscription::create([
                'restaurant_id' => $restaurant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'ended_at' => $endedAt,
                'renewal_at' => $endedAt,
                'price' => $plan->price,
                'original_price' => $plan->price,
                'billing_cycle' => $plan->billing_cycle,
                'meta' => ['source' => 'custom_plan_builder'],
            ]);

            // 5. Cập nhật Restaurant
            $restaurant->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'subscription_ends_at' => $endedAt->toDateString(),
                'subscription_started_at' => now()->toDateString(),
            ]);

            // 6. Ghi Audit Log
            AuditLog::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => null,
                'user_id' => $request->user()->id,
                'user_role' => 'admin',
                'event' => 'created',
                'action' => 'custom_plan_builder_apply',
                'subject_type' => Restaurant::class,
                'subject_id' => $restaurant->id,
                'old_values' => [],
                'new_values' => [
                    'plan_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'price' => $plan->price,
                    'billing_cycle' => $plan->billing_cycle,
                    'max_branches' => $plan->max_branches,
                    'max_users' => $plan->max_users,
                    'max_tables' => $plan->max_tables,
                    'max_dishes' => $plan->max_dishes,
                    'features' => $plan->features,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        // Xóa Cache
        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', 'Đã thiết lập và áp dụng gói dịch vụ tùy chỉnh cho doanh nghiệp thành công.');
    }
}
