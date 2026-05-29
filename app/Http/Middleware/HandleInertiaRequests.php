<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
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

        // Clear Spatie permission cache để đảm bảo roles luôn mới nhất
        if ($user) {
            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        $roles = $user?->getRoleNames() ?? [];

        $tenant = null;
        if ($user && $user->restaurant_id) {
            $restaurant = $user->restaurant;
            if ($restaurant) {
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
                    'quota_summary' => app(\App\Services\QuotaService::class)->getSummary($restaurant),
                ];
            }
        }

        $availablePlans = SubscriptionPlan::where('status', 'active')
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

        // Không gán trực tiếp roles vào user để tránh lỗi update DB
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'roles'           => $roles,
            'tenant'          => $tenant,
            'available_plans' => $availablePlans,
            'pendingApprovalCount' => $user?->hasRole('owner') && $user->restaurant_id
                ? ApprovalRequest::where('restaurant_id', $user->restaurant_id)->where('status', 'pending')->count()
                : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success'      => $request->session()->get('success'),
                'error'        => $request->session()->get('error'),
                'temp_password' => $request->session()->get('temp_password'),
            ],
        ];
    }
}
