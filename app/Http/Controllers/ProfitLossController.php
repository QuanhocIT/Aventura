<?php

namespace App\Http\Controllers;

use App\Services\ProfitLossService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitLossController extends Controller
{
    public function index(Request $request, ProfitLossService $service, TenantContext $tenantContext): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        abort_unless($restaurantId, 403, 'Không tìm thấy nhà hàng.');

        $data = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);
        $branchId = $tenantContext->activeBranchId();

        return Inertia::render('reports/ProfitLoss', [
            'report' => $service->buildWithComparison($restaurantId, $year, $month, $branchId),
            'filters' => ['year' => $year, 'month' => $month],
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }
}
