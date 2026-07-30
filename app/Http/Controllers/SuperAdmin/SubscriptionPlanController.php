<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::withTrashed()->where('is_custom', false)->withCount('restaurants')->get()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'price' => $p->price,
            'billing_cycle' => $p->billing_cycle,
            'max_branches' => $p->max_branches,
            'max_tables' => $p->max_tables,
            'max_users' => $p->max_users,
            'features' => $p->features ?? [],
            'status' => $p->trashed() ? 'inactive' : $p->status,
            'is_deleted' => $p->trashed(),
            'deleted_at' => $p->deleted_at?->format('d/m/Y H:i'),
            'restaurants_count' => $p->restaurants_count,
        ]);

        return Inertia::render('super-admin/plans/Index', ['plans' => $plans]);
    }

    private array $featureFlags = [
        // 1. Vận hành & POS
        'kitchen_display', 'qr_ordering', 'inventory_basic', 'supplier_portal', 'kiosk_mode', 'table_reservation',
        // 2. Nhân sự & Ca làm
        'hr_timekeeping', 'hr_full', 'shift_management',
        // 3. CRM & Marketing
        'crm_loyalty', 'marketing_campaign', 'custom_domain',
        // 4. Báo cáo & Tự động hóa
        'advanced_analytics', 'realtime', 'fraud_detection', 'email_reports',
        // 5. AI Features
        'ai_advisor', 'ai_forecasting',
        // 6. Tích hợp & Enterprise
        'delivery_integration', 'e_invoice', 'multi_branch_sync', 'multi_currency', 'api_access', 'automated_backup',
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:subscription_plans,code|alpha_dash',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'billing_cycle' => 'required|in:monthly,yearly,quarterly',
            'price' => 'required|integer|min:0',
            'max_branches' => 'required|integer|min:-1',
            'max_tables' => 'required|integer|min:-1',
            'max_users' => 'required|integer|min:-1',
            'max_areas' => 'required|integer|min:-1',
            'max_storage_mb' => 'required|integer|min:1',
            'api_rate_limit' => 'required|integer|min:10',
            'yearly_discount_percent' => 'required|integer|min:0|max:100',
            ...(array_fill_keys($this->featureFlags, 'boolean')),
        ]);

        $toNull = fn ($v) => $v === -1 ? null : $v;

        $features = [
            'description' => $validated['description'] ?? null,
            'max_areas' => $validated['max_areas'],
            'max_storage_mb' => $validated['max_storage_mb'],
            'api_rate_limit' => $validated['api_rate_limit'],
            'yearly_discount_percent' => $validated['yearly_discount_percent'],
        ];
        foreach ($this->featureFlags as $flag) {
            $features[$flag] = (bool) ($validated[$flag] ?? false);
        }

        $plan = SubscriptionPlan::create([
            'code' => Str::lower($validated['code']),
            'name' => $validated['name'],
            'price' => $validated['price'],
            'billing_cycle' => $validated['billing_cycle'],
            'max_branches' => $toNull($validated['max_branches']),
            'max_tables' => $toNull($validated['max_tables']),
            'max_users' => $toNull($validated['max_users']),
            'status' => 'active',
            'features' => $features,
        ]);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'subscription_plan_create',
            'subject_type' => SubscriptionPlan::class,
            'subject_id' => $plan->id,
            'old_values' => [],
            'new_values' => $plan->only(['code', 'name', 'price', 'billing_cycle', 'max_branches', 'max_tables', 'max_users', 'features']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        Cache::forget('active_plans');
        Cache::forget('subscription_plans_active');
        DashboardController::forgetCache();

        return back()->with('success', "Đã tạo gói \"{$plan->name}\" thành công.");
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'price' => 'required|integer|min:0',
            'max_branches' => 'required|integer|min:-1',
            'max_tables' => 'required|integer|min:-1',
            'max_users' => 'required|integer|min:-1',
            'max_areas' => 'required|integer|min:-1',
            'max_storage_mb' => 'required|integer|min:1',
            'api_rate_limit' => 'required|integer|min:10',
            'yearly_discount_percent' => 'required|integer|min:0|max:100',
            ...(array_fill_keys($this->featureFlags, 'boolean')),
        ]);

        $toNull = fn ($v) => $v === -1 ? null : $v;

        $existingFeatures = $plan->features ?? [];
        $oldValues = ['name' => $plan->name, 'price' => $plan->price, 'max_branches' => $plan->max_branches, 'max_tables' => $plan->max_tables, 'max_users' => $plan->max_users, 'features' => $existingFeatures];

        $newFeatures = array_merge($existingFeatures, [
            'description' => $validated['description'] ?? null,
            'max_areas' => $validated['max_areas'],
            'max_storage_mb' => $validated['max_storage_mb'],
            'api_rate_limit' => $validated['api_rate_limit'],
            'yearly_discount_percent' => $validated['yearly_discount_percent'],
        ]);
        foreach ($this->featureFlags as $flag) {
            $newFeatures[$flag] = (bool) ($validated[$flag] ?? false);
        }

        $plan->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'max_branches' => $toNull($validated['max_branches']),
            'max_tables' => $toNull($validated['max_tables']),
            'max_users' => $toNull($validated['max_users']),
            'features' => $newFeatures,
        ]);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'subscription_plan_update',
            'subject_type' => SubscriptionPlan::class,
            'subject_id' => $plan->id,
            'old_values' => $oldValues,
            'new_values' => $plan->only(['name', 'price', 'max_branches', 'max_tables', 'max_users', 'features']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        Cache::forget('active_plans');
        Cache::forget('subscription_plans_active');
        DashboardController::forgetCache();

        return back()->with('success', "Đã cập nhật gói {$plan->name}.");
    }

    public function planRestaurants(SubscriptionPlan $plan): JsonResponse
    {
        $restaurants = $plan->restaurants()
            ->with('owner')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'owner_name' => $r->owner?->name ?? 'N/A',
                'owner_email' => $r->owner?->email ?? 'N/A',
                'subscription_ends_at' => $r->subscription_ends_at ? Carbon::parse($r->subscription_ends_at)->format('d/m/Y') : 'N/A',
            ]);

        return response()->json([
            'restaurants' => $restaurants,
        ]);
    }

    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $oldStatus = $plan->status;
        $plan->update(['status' => 'inactive']);
        $plan->delete();

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'deleted',
            'action' => 'subscription_plan_delete',
            'subject_type' => SubscriptionPlan::class,
            'subject_id' => $plan->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'inactive', 'deleted_at' => now()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        Cache::forget('active_plans');
        Cache::forget('subscription_plans_active');
        DashboardController::forgetCache();

        return back()->with('success', "Đã ngừng cung cấp gói \"{$plan->name}\" (Xóa mềm). Khách hàng đã đăng ký vẫn được tiếp tục sử dụng.");
    }

    public function restore(Request $request, int|string $id): RedirectResponse
    {
        $plan = SubscriptionPlan::withTrashed()->findOrFail($id);
        $deletedAt = $plan->deleted_at;
        $plan->restore();
        $plan->update(['status' => 'active']);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'subscription_plan_restore',
            'subject_type' => SubscriptionPlan::class,
            'subject_id' => $plan->id,
            'old_values' => ['status' => 'inactive', 'deleted_at' => $deletedAt],
            'new_values' => ['status' => 'active', 'deleted_at' => null],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        Cache::forget('active_plans');
        Cache::forget('subscription_plans_active');
        DashboardController::forgetCache();

        return back()->with('success', "Đã khôi phục và tiếp tục cung cấp gói \"{$plan->name}\".");
    }
}
