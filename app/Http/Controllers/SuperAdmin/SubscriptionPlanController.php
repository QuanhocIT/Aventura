<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return back()->with('success', "Đã cập nhật gói {$plan->name}.");
    }
}
