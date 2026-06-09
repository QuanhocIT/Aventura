<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::withCount('restaurants')->get()->map(fn ($p) => [
            'id'              => $p->id,
            'code'            => $p->code,
            'name'            => $p->name,
            'price'           => $p->price,
            'billing_cycle'   => $p->billing_cycle,
            'max_branches'    => $p->max_branches,
            'max_tables'      => $p->max_tables,
            'max_users'       => $p->max_users,
            'features'        => $p->features ?? [],
            'status'          => $p->status,
            'restaurants_count' => $p->restaurants_count,
        ]);

        return Inertia::render('super-admin/plans/Index', ['plans' => $plans]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'               => 'required|string|max:50|unique:subscription_plans,code|alpha_dash',
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string|max:255',
            'billing_cycle'      => 'required|in:monthly,yearly,quarterly',
            'price'              => 'required|integer|min:0',
            'max_branches'       => 'required|integer|min:-1',
            'max_tables'         => 'required|integer|min:-1',
            'max_users'          => 'required|integer|min:-1',
            'max_areas'          => 'required|integer|min:-1',
            'max_storage_mb'     => 'required|integer|min:1',
            'ai_features'        => 'boolean',
            'realtime'           => 'boolean',
            'advanced_analytics' => 'boolean',
            'api_rate_limit'     => 'required|integer|min:10',
        ]);

        $toNull = fn ($v) => $v === -1 ? null : $v;

        $plan = SubscriptionPlan::create([
            'code'          => Str::lower($validated['code']),
            'name'          => $validated['name'],
            'price'         => $validated['price'],
            'billing_cycle' => $validated['billing_cycle'],
            'max_branches'  => $toNull($validated['max_branches']),
            'max_tables'    => $toNull($validated['max_tables']),
            'max_users'     => $toNull($validated['max_users']),
            'status'        => 'active',
            'features'      => [
                'description'        => $validated['description'] ?? null,
                'max_areas'          => $validated['max_areas'],
                'max_storage_mb'     => $validated['max_storage_mb'],
                'ai_features'        => (bool) ($validated['ai_features'] ?? false),
                'realtime'           => (bool) ($validated['realtime'] ?? false),
                'advanced_analytics' => (bool) ($validated['advanced_analytics'] ?? false),
                'api_rate_limit'     => $validated['api_rate_limit'],
            ],
        ]);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'created',
            'action'        => 'subscription_plan_create',
            'subject_type'  => SubscriptionPlan::class,
            'subject_id'    => $plan->id,
            'old_values'    => [],
            'new_values'    => $plan->only(['code', 'name', 'price', 'billing_cycle', 'max_branches', 'max_tables', 'max_users', 'features']),
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã tạo gói \"{$plan->name}\" thành công.");
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:100',
            'description'        => 'nullable|string|max:255',
            'price'              => 'required|integer|min:0',
            'max_branches'       => 'required|integer|min:-1',
            'max_tables'         => 'required|integer|min:-1',
            'max_users'          => 'required|integer|min:-1',
            'max_areas'          => 'required|integer|min:-1',
            'max_storage_mb'     => 'required|integer|min:1',
            'ai_features'        => 'boolean',
            'realtime'           => 'boolean',
            'advanced_analytics' => 'boolean',
            'api_rate_limit'     => 'required|integer|min:10',
        ]);

        $toNull = fn ($v) => $v === -1 ? null : $v;

        $existingFeatures = $plan->features ?? [];
        $oldValues = [
            'name' => $plan->name,
            'price' => $plan->price,
            'max_branches' => $plan->max_branches,
            'max_tables' => $plan->max_tables,
            'max_users' => $plan->max_users,
            'features' => $existingFeatures,
        ];

        $plan->update([
            'name'         => $validated['name'],
            'price'        => $validated['price'],
            'max_branches' => $toNull($validated['max_branches']),
            'max_tables'   => $toNull($validated['max_tables']),
            'max_users'    => $toNull($validated['max_users']),
            'features'     => array_merge($existingFeatures, [
                'description'         => $validated['description'] ?? null,
                'max_areas'           => $validated['max_areas'],
                'max_storage_mb'      => $validated['max_storage_mb'],
                'ai_features'         => (bool) ($validated['ai_features'] ?? false),
                'realtime'            => (bool) ($validated['realtime'] ?? false),
                'advanced_analytics'  => (bool) ($validated['advanced_analytics'] ?? false),
                'api_rate_limit'      => $validated['api_rate_limit'],
            ]),
        ]);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => $request->user()->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'subscription_plan_update',
            'subject_type'  => SubscriptionPlan::class,
            'subject_id'    => $plan->id,
            'old_values'    => $oldValues,
            'new_values'    => $plan->only(['name', 'price', 'max_branches', 'max_tables', 'max_users', 'features']),
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        Cache::forget('superadmin_ai_insights');
        DashboardController::forgetCache();

        return back()->with('success', "Đã cập nhật gói {$plan->name}.");
    }

    public function planRestaurants(SubscriptionPlan $plan): \Illuminate\Http\JsonResponse
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
                'subscription_ends_at' => $r->subscription_ends_at ? \Illuminate\Support\Carbon::parse($r->subscription_ends_at)->format('d/m/Y') : 'N/A',
            ]);

        return response()->json([
            'restaurants' => $restaurants
        ]);
    }
}
