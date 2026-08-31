<?php

namespace App\Http\Controllers;

use App\Services\MenuInsightService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuInsightController extends Controller
{
    public function __construct(
        private MenuInsightService $service,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'ai_advisor')) {
            return response()->json(['error' => 'Tính năng AI Tư vấn yêu cầu gói Chuyên Nghiệp trở lên.', 'feature' => 'ai_advisor'], 403);
        }

        $restaurantId = $user->restaurant_id;
        $days = (int) $request->input('days', 30);
        $branchId = $this->tenantContext->activeBranchId();

        return response()->json([
            'insights' => $this->service->getInsights($restaurantId, $days, $branchId),
            'bcg' => $this->service->getBcgData($restaurantId, $days, $branchId),
            'margins' => $this->service->getProductMargins($restaurantId, $days, $branchId),
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }
}
