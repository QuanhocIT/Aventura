<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourceLimitController extends Controller
{
    public function __construct(private readonly QuotaService $quota) {}

    public function index(Request $request): Response
    {
        $query = Restaurant::with(['plan', 'owner'])
            ->whereIn('status', ['active', 'suspended']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
            );
        }

        $restaurants = $query->latest()->get()->map(function (Restaurant $r) {
            $summary = $this->quota->getSummary($r);

            // Determine if any resource is over 80% or 100%
            $hasOverlimit = false;
            $hasWarning = false;

            foreach ($summary['resources'] as $res => $data) {
                if (! $data['unlimited']) {
                    if ($data['used'] >= $data['limit']) {
                        $hasOverlimit = true;
                    } elseif ($data['used'] >= ($data['limit'] * 0.8)) {
                        $hasWarning = true;
                    }
                }
            }

            return [
                'id' => $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'status' => $r->status,
                'plan_name' => $summary['plan'],
                'owner_name' => $r->owner?->name ?? '—',
                'owner_email' => $r->owner?->email ?? $r->email ?? '—',
                'resources' => $summary['resources'],
                'has_overlimit' => $hasOverlimit,
                'has_warning' => $hasWarning,
            ];
        });

        // Filter overlimit if requested
        if ($request->boolean('overlimit')) {
            $restaurants = $restaurants->filter(fn ($r) => $r['has_overlimit']);
        }

        return Inertia::render('super-admin/ResourceLimits', [
            'restaurants' => $restaurants->values(),
            'filters' => $request->only(['search', 'overlimit']),
        ]);
    }
}
