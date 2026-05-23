<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SupportPortalService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected SupportPortalService $supportPortal) {}

    public function index(): Response
    {
        $stats = [
            'total_restaurants' => Restaurant::count(),
            'active'            => Restaurant::where('status', 'active')->count(),
            'suspended'         => Restaurant::where('status', 'suspended')->count(),
            'expired'           => Restaurant::where('status', 'expired')->count(),
            'total_users'       => User::count(),
            'pro_plan'          => Restaurant::whereHas('plan', fn ($q) => $q->where('code', 'PRO'))->count(),
        ];

        $recentRestaurants = Restaurant::with(['plan', 'owner'])
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'status'     => $r->status,
                'plan'       => $r->plan?->name ?? '—',
                'plan_code'  => $r->plan?->code ?? 'FREE',
                'owner'      => $r->owner?->name ?? '—',
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
            'stats'             => $stats,
            'recentRestaurants' => $recentRestaurants,
            'planDistribution'  => $planDistribution,
            'supportOverview'   => [
                'monitoring' => $this->supportPortal->monitoringSnapshot(),
                'stats' => $this->supportPortal->dashboardMetrics(),
            ],
        ]);
    }
}