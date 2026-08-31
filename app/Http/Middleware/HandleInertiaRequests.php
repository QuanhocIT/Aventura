<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use App\Models\SubscriptionPlan;
use App\Models\SystemMaintenanceSchedule;
use App\Services\ApprovalAuthorityService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenantContext = app(TenantContext::class);
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

        // Spatie tự động invalidate cache khi roles/permissions thay đổi
        // Không cần forgetCachedPermissions() trên mỗi request

        $roles = $user?->getRoleNames() ?? [];

        $tenant = null;
        if ($user && $user->restaurant_id && ! $isSuperAdmin) {
            $restaurant = $user->restaurant;
            if ($restaurant) {
                // Cache branches per restaurant (5 phút) — không query DB mỗi request
                $branches = $user->canViewAllBranches()
                    ? Cache::remember(
                        "tenant_branches:v3:{$restaurant->id}:all",
                        300,
                        fn () => $restaurant->branches()
                            ->select('id', 'name')
                            ->get()
                            ->toArray()
                    )
                    : ($tenantContext->isBranchScoped()
                        ? $restaurant->branches()
                            ->whereKey($tenantContext->activeBranchId())
                            ->select('id', 'name')
                            ->get()
                            ->toArray()
                        : []);

                $tenant = [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'status' => $restaurant->status,
                    'lifecycle_status' => $restaurant->lifecycleStatus(),
                    'trial_ends_at' => $restaurant->trial_ends_at?->toDateString(),
                    'subscription_ends_at' => $restaurant->subscription_ends_at?->toDateString(),
                    'plan' => $restaurant->plan ? [
                        'code' => $restaurant->plan->code,
                        'name' => $restaurant->plan->name,
                        'max_branches' => $restaurant->plan->max_branches,
                        'max_tables' => $restaurant->plan->max_tables,
                        'max_users' => $restaurant->plan->max_users,
                        'features' => $restaurant->plan->features,
                    ] : null,
                    'quota_summary' => Cache::remember(
                        "quota_summary:{$restaurant->id}",
                        120, // 2 phút
                        fn () => app(QuotaService::class)->getSummary($restaurant)
                    ),
                    'branches' => $branches,
                    'active_branch_id' => $tenantContext->activeBranchId(),
                    'scope' => $tenantContext->scope(),
                    'scope_key' => $tenantContext->scopeKey(),
                ];
            }
        }

        // Super Admin không hiển thị subscription widget nên không cần đọc và
        // serialize toàn bộ danh sách plan vào mọi response Inertia.
        // Subscription plans chỉ cần thiết ở các trang thanh toán/nâng cấp/chọn nhà hàng
        $needsPlans = $request->is('billing*') || $request->is('choose-restaurant*');
        $availablePlans = ($isSuperAdmin || ! $needsPlans) ? [] : Cache::remember('subscription_plans_active', 3600, function () {
            $plans = SubscriptionPlan::where('status', 'active')
                ->orderBy('price')
                ->get()
                ->map(fn (SubscriptionPlan $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'price' => (int) $p->price,
                    'billing_cycle' => $p->billing_cycle,
                    'max_branches' => $p->max_branches,
                    'max_tables' => $p->max_tables,
                    'max_users' => $p->max_users,
                    'features' => $p->features ?? [],
                ]);

            return array_values(collect($plans)->toArray());
        });

        // Chỉ expose các trường an toàn cần thiết — không dùng toArray() rãi rác
        $safeUser = $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $user->getRawOriginal('branch_id'),
            'assigned_branch_id' => $user->assignedBranchId(),
            'supplier_id' => $user->supplier_id,
            'status' => $user->status,
            'onboarding_status' => $user->onboarding_status,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'referral_code' => $user->referral_code,
            'has_pin' => ! empty($user->pin_code), // chỉ gửi boolean, không gửi hash
            'two_factor_enabled' => ! empty($user->two_factor_confirmed_at),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'roles' => $roles->toArray(),
        ] : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $safeUser,
                'shift_allowed_until' => $user && ! $user->isExemptFromShiftLock()
                    ? session('shift_allowed_until')
                    : null,
                'shift_lock_exempt' => $user?->isExemptFromShiftLock() ?? false,
            ],
            'roles' => $roles,
            'supplier_portal_enabled' => (bool) config('portal.supplier_portal_enabled', false),
            'tenant' => $tenant,
            'available_plans' => $availablePlans,
            'pendingApprovalCount' => Inertia::defer(fn () => $this->pendingApprovalCount($user)),
            'myOpenRequestCount' => Inertia::defer(fn () => $this->myOpenRequestCount($user)),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'is_impersonating' => $request->session()->has('impersonate_original_user_id'),
            'impersonate_read_only' => $request->session()->has('impersonate_original_user_id') && (! $request->session()->get('impersonate_write_until') || now()->timestamp >= $request->session()->get('impersonate_write_until')),
            'impersonate_write_until' => $request->session()->get('impersonate_write_until'),
            'impersonate_reason' => $request->session()->get('impersonate_reason'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
                'temp_password' => $request->session()->get('temp_password'),
                'webhook_secret' => $request->session()->get('webhook_secret'),
            ],
            'locale' => app()->getLocale(),
            'upcoming_maintenance' => Cache::remember('upcoming_maintenance', 60, fn () => SystemMaintenanceSchedule::whereIn('status', ['scheduled', 'active'])
                ->where('downtime_start', '<=', now()->addHours(24))
                ->where('downtime_end', '>', now())
                ->first()?->only(['title', 'downtime_start', 'downtime_end', 'banner_message', 'status', 'services'])
            ),
        ];
    }

    /**
     * Số yêu cầu đang chờ người này xử lý.
     *
     * Trước đây chỉ đếm cho Chủ, nên Quản lý chi nhánh không hề thấy badge dù
     * đã có quyền duyệt. Quản lý chỉ đếm phần thuộc chi nhánh mình.
     */
    private function pendingApprovalCount(?object $user): int
    {
        if (! $user || ! $user->restaurant_id) {
            return 0;
        }

        $isOwner = $user->isOwner() || $user->isSuperAdmin();

        if (! $isOwner && ! $user->isBranchManager()) {
            return 0;
        }

        $cacheKey = $isOwner
            ? "pending_approvals:{$user->restaurant_id}"
            : "pending_approvals:{$user->restaurant_id}:u{$user->id}";

        return Cache::remember($cacheKey, 60, function () use ($user, $isOwner): int {
            $query = ApprovalRequest::where('restaurant_id', $user->restaurant_id)
                ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED]);

            if (! $isOwner) {
                // Yêu cầu đã đẩy lên Chủ thì không còn nằm trong việc của Quản lý.
                $query->where('status', ApprovalRequest::STATUS_PENDING)
                    ->whereIn('branch_id', app(ApprovalAuthorityService::class)->managedBranchIds($user));
            }

            return $query->count();
        });
    }

    /**
     * Số yêu cầu của chính người này còn đang chờ kết quả — áp dụng cho mọi vai trò.
     */
    private function myOpenRequestCount(?object $user): int
    {
        if (! $user || ! $user->restaurant_id) {
            return 0;
        }

        return Cache::remember(
            "my_open_requests:{$user->id}",
            60,
            fn () => ApprovalRequest::where('restaurant_id', $user->restaurant_id)
                ->where('requester_id', $user->id)
                ->whereIn('status', [ApprovalRequest::STATUS_PENDING, ApprovalRequest::STATUS_ESCALATED])
                ->count()
        );
    }
}
