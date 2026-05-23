<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SupportPortalService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected SupportPortalService $supportPortal) {}

    public function index(): Response
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $windowStart = $now->copy()->subDays(30);

        $subscriptions = RestaurantSubscription::with(['plan', 'restaurant'])
            ->orderBy('restaurant_id')
            ->orderBy('started_at')
            ->get();

        $activeSubscriptions = $subscriptions->filter(fn (RestaurantSubscription $subscription) => $subscription->isActive());
        $mrr = $activeSubscriptions->sum(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription));

        $cancelledThisMonth = $subscriptions
            ->filter(fn (RestaurantSubscription $subscription) => $subscription->status === 'cancelled'
                && $subscription->cancelled_at
                && $subscription->cancelled_at->between($monthStart, $monthEnd))
            ->pluck('restaurant_id')
            ->unique()
            ->count();

        $activeBase = max(1, Restaurant::count());
        $freeToProConversionsThisMonth = $this->freeToProConversionsForMonth($subscriptions, $monthStart, $monthEnd);

        $stats = [
            'total_restaurants' => Restaurant::count(),
            'active'            => Restaurant::where('status', 'active')->count(),
            'suspended'         => Restaurant::where('status', 'suspended')->count(),
            'expired'           => Restaurant::where('status', 'expired')->count(),
            'total_users'       => User::count(),
            'pro_plan'          => Restaurant::whereHas('plan', fn ($q) => $q->whereRaw('LOWER(code) = ?', ['pro']))->count(),
        ];

        $recentRestaurants = Restaurant::with(['plan', 'owner'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'status'     => $r->status,
                'plan'       => $r->plan?->name ?? '-',
                'plan_code'  => $r->plan?->code ?? 'free',
                'owner'      => $r->owner?->name ?? '-',
                'created_at' => $r->created_at->format('d/m/Y'),
            ]);

        $planDistribution = SubscriptionPlan::withCount('restaurants')
            ->get()
            ->map(fn ($p) => [
                'name'  => $p->name,
                'code'  => $p->code,
                'count' => $p->restaurants_count,
            ]);

        return Inertia::render('super-admin/Dashboard', [
            'stats' => $stats,
            'saasMetrics' => [
                'mrr' => round($mrr),
                'arr' => round($mrr * 12),
                'churn_rate' => round(($cancelledThisMonth / $activeBase) * 100, 2),
                'churned_this_month' => $cancelledThisMonth,
                'active_subscriptions' => $activeSubscriptions->pluck('restaurant_id')->unique()->count(),
                'paid_tenants' => $activeSubscriptions
                    ->filter(fn (RestaurantSubscription $subscription) => $this->monthlyRecurringRevenue($subscription) > 0)
                    ->pluck('restaurant_id')
                    ->unique()
                    ->count(),
            ],
            'tenantGrowth' => $this->tenantGrowthSeries($now, $subscriptions),
            'resourceInsights' => [
                'top_order_restaurants' => $this->topOrderRestaurants($windowStart),
                'top_storage_restaurants' => $this->topStorageRestaurants(),
                'totals' => [
                    'orders_last_30_days' => Order::where('created_at', '>=', $windowStart)->count(),
                    'storage_bytes' => (int) MediaAsset::sum('size_bytes'),
                ],
            ],
            'recentRestaurants' => $recentRestaurants,
            'planDistribution' => $planDistribution,
            'supportOverview' => [
                'monitoring' => $this->supportPortal->monitoringSnapshot(),
                'stats' => $this->supportPortal->dashboardMetrics(),
            ],
        ]);
    }

    protected function monthlyRecurringRevenue(RestaurantSubscription $subscription): float
    {
        $price = (float) ($subscription->price > 0 ? $subscription->price : ($subscription->plan?->price ?? 0));
        $cycle = strtolower((string) ($subscription->plan?->billing_cycle ?? 'monthly'));

        return match ($cycle) {
            'yearly' => $price / 12,
            'quarterly' => $price / 3,
            default => $price,
        };
    }

    protected function freeToProConversionsForMonth($subscriptions, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        return $subscriptions
            ->groupBy('restaurant_id')
            ->filter(function ($restaurantSubscriptions) use ($monthStart, $monthEnd) {
                $hasFreeBefore = $restaurantSubscriptions->contains(function (RestaurantSubscription $subscription) {
                    return strtolower((string) $subscription->plan?->code) === 'free';
                });

                if (! $hasFreeBefore) {
                    return false;
                }

                return $restaurantSubscriptions->contains(function (RestaurantSubscription $subscription) use ($monthStart, $monthEnd) {
                    return strtolower((string) $subscription->plan?->code) === 'pro'
                        && $subscription->started_at
                        && $subscription->started_at->between($monthStart, $monthEnd);
                });
            })
            ->count();
    }

    protected function tenantGrowthSeries(CarbonInterface $now, $subscriptions): array
    {
        return collect(range(5, 0))
            ->map(function (int $offset) use ($now, $subscriptions) {
                $month = $now->copy()->subMonths($offset)->startOfMonth();
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();

                $newTenants = Restaurant::whereBetween('created_at', [$start, $end])->count();
                $freeToPro = $this->freeToProConversionsForMonth(
                    $subscriptions->filter(fn (RestaurantSubscription $subscription) => $subscription->started_at),
                    $start,
                    $end,
                );

                return [
                    'label' => $month->format('m/Y'),
                    'month' => $month->format('Y-m'),
                    'new_tenants' => $newTenants,
                    'free_to_pro' => $freeToPro,
                    'conversion_rate' => $newTenants > 0 ? round(($freeToPro / $newTenants) * 100, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    protected function topOrderRestaurants(CarbonInterface $windowStart): array
    {
        return Order::query()
            ->select('restaurant_id', DB::raw('COUNT(*) as orders_count'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('restaurant_id')
            ->orderByDesc('orders_count')
            ->with('restaurant:id,name,code')
            ->take(5)
            ->get()
            ->map(fn (Order $order) => [
                'restaurant_id' => $order->restaurant_id,
                'name' => $order->restaurant?->name ?? 'Tenant #'.$order->restaurant_id,
                'code' => $order->restaurant?->code ?? null,
                'orders_count' => (int) $order->orders_count,
            ])
            ->all();
    }

    protected function topStorageRestaurants(): array
    {
        return MediaAsset::query()
            ->select('restaurant_id', DB::raw('SUM(size_bytes) as storage_bytes'), DB::raw('COUNT(*) as files_count'))
            ->whereNotNull('restaurant_id')
            ->groupBy('restaurant_id')
            ->orderByDesc('storage_bytes')
            ->with('restaurant:id,name,code')
            ->take(5)
            ->get()
            ->map(fn (MediaAsset $asset) => [
                'restaurant_id' => $asset->restaurant_id,
                'name' => $asset->restaurant?->name ?? 'Tenant #'.$asset->restaurant_id,
                'code' => $asset->restaurant?->code ?? null,
                'storage_bytes' => (int) $asset->storage_bytes,
                'files_count' => (int) $asset->files_count,
            ])
            ->all();
    }
}



