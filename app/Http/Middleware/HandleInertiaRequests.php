<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Spatie tự động invalidate cache khi roles/permissions thay đổi
        // Không cần forgetCachedPermissions() trên mỗi request

        $roles = $user?->getRoleNames() ?? [];

        $tenant = null;
        if ($user && $user->restaurant_id) {
            $restaurant = $user->restaurant;
            if ($restaurant) {
                // Cache branches per restaurant (5 phút) — không query DB mỗi request
                $branches = ($user->hasAnyRole(['owner', 'manager']))
                    ? Cache::remember(
                        "tenant_branches:{$restaurant->id}",
                        300,
                        fn () => $restaurant->branches()->select('id', 'name')->get()->toArray()
                    )
                    : [];

                $tenant = [
                    'id'                   => $restaurant->id,
                    'name'                 => $restaurant->name,
                    'status'               => $restaurant->status,
                    'trial_ends_at'        => $restaurant->trial_ends_at?->toDateString(),
                    'subscription_ends_at' => $restaurant->subscription_ends_at?->toDateString(),
                    'plan' => $restaurant->plan ? [
                        'code'         => $restaurant->plan->code,
                        'name'         => $restaurant->plan->name,
                        'max_branches' => $restaurant->plan->max_branches,
                        'max_tables'   => $restaurant->plan->max_tables,
                        'max_users'    => $restaurant->plan->max_users,
                        'features'     => $restaurant->plan->features,
                    ] : null,
                    'quota_summary' => Cache::remember(
                        "quota_summary:{$restaurant->id}",
                        120, // 2 phút
                        fn () => app(\App\Services\QuotaService::class)->getSummary($restaurant)
                    ),
                    'branches'             => $branches,
                    'active_branch_id'     => $user->branch_id,
                ];
            }
        }

        $availablePlans = Cache::remember('subscription_plans_active', 3600, function () {
            return SubscriptionPlan::where('status', 'active')
                ->orderBy('price')
                ->get()
                ->map(fn (SubscriptionPlan $p) => [
                    'id'            => $p->id,
                    'code'          => $p->code,
                    'name'          => $p->name,
                    'price'         => (int) $p->price,
                    'billing_cycle' => $p->billing_cycle,
                    'max_branches'  => $p->max_branches,
                    'max_tables'    => $p->max_tables,
                    'max_users'     => $p->max_users,
                    'features'      => $p->features ?? [],
                ])
                ->values()
                ->all();
        });

        // Chỉ expose các trường an toàn cần thiết — không dùng toArray() rãi rác
        $safeUser = $user ? [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'avatar_url'        => $user->avatar_url,
            'restaurant_id'     => $user->restaurant_id,
            'branch_id'         => $user->branch_id,
            'supplier_id'       => $user->supplier_id,
            'status'            => $user->status,
            'onboarding_status' => $user->onboarding_status,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at'     => $user->last_login_at,
            'referral_code'     => $user->referral_code,
            'has_pin'           => !empty($user->pin_code), // chỉ gửi boolean, không gửi hash
            'two_factor_enabled'=> !empty($user->two_factor_confirmed_at),
            'permissions' => Cache::remember(
                "user_permissions:{$user->id}",
                300,
                fn () => $user->getAllPermissions()->pluck('name')->toArray()
            ),
            'roles' => $roles->toArray(),
        ] : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $safeUser,
                'shift_allowed_until' => session('shift_allowed_until'),
            ],
            'roles'           => $roles,
            'tenant'          => $tenant,
            'available_plans' => $availablePlans,
            'pendingApprovalCount' => $user?->hasRole('owner') && $user->restaurant_id
                ? Cache::remember(
                    "pending_approvals:{$user->restaurant_id}",
                    60, // 1 phút
                    fn () => ApprovalRequest::where('restaurant_id', $user->restaurant_id)->where('status', 'pending')->count()
                )
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'is_impersonating' => $request->session()->has('impersonate_original_user_id'),
            'impersonate_read_only' => $request->session()->has('impersonate_original_user_id') && (!$request->session()->get('impersonate_write_until') || now()->timestamp >= $request->session()->get('impersonate_write_until')),
            'impersonate_write_until' => $request->session()->get('impersonate_write_until'),
            'impersonate_reason' => $request->session()->get('impersonate_reason'),
            'flash' => [
                'success'      => $request->session()->get('success'),
                'error'        => $request->session()->get('error'),
                'temp_password' => $request->session()->get('temp_password'),
                'webhook_secret' => $request->session()->get('webhook_secret'),
            ],
            'locale' => app()->getLocale(),
            'service_maintenance' => json_decode(@file_get_contents(storage_path('framework/service-maintenance.json')), true) ?: [],
            'upcoming_maintenance' => Cache::remember('upcoming_maintenance', 60, fn () =>
                \App\Models\SystemMaintenanceSchedule::whereIn('status', ['scheduled', 'active'])
                    ->where('downtime_start', '<=', now()->addHours(24))
                    ->where('downtime_end', '>', now())
                    ->first()?->only(['title', 'downtime_start', 'downtime_end', 'banner_message', 'status', 'services'])
            ),
        ];
    }
}
