<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\RestaurantBranch;
use App\Models\SalaryAdjustment;
use App\Services\QuotaService;
use App\Services\WasteAnalyticsService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WasteManagementController extends Controller
{
    public function __construct(private WasteAnalyticsService $analytics) {}

    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Hao hụt & Lãng phí',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $days = max(1, min(365, (int) ($request->days ?? 30)));
        $branchId = $tenantContext->activeBranchId();
        $periodEnd = now();
        $periodStart = $periodEnd->copy()->subDays($days);
        $scopeLabel = $branchId
            ? (RestaurantBranch::where('restaurant_id', $restaurantId)->whereKey($branchId)->value('name') ?? 'Chi nhánh đang chọn')
            : 'Toàn chuỗi';

        $dashboard = $this->analytics->getDashboard($restaurantId, $days, $branchId);
        $trend = $this->analytics->getTrendData($restaurantId, 6, $branchId);
        $suggestions = $this->analytics->getAiSuggestions($restaurantId, $branchId, $days);
        $expiring = $this->analytics->getExpiringItems($restaurantId, 3, $branchId);

        $stockMap = Inventory::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->groupBy('ingredient_id')
            ->map(fn ($group) => (float) $group->sum('quantity_on_hand'));

        $ingredients = Ingredient::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->with('unit')
            ->orderBy('name')
            ->get()
            ->map(function ($ing) use ($stockMap) {
                return [
                    'id' => $ing->id,
                    'name' => $ing->name,
                    'average_cost' => (float) $ing->average_cost,
                    'unit' => $ing->unit ? ['id' => $ing->unit->id, 'symbol' => $ing->unit->symbol] : null,
                    'stock' => (float) ($stockMap->get($ing->id) ?? 0.0),
                ];
            });

        $employees = Employee::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'job_title']);

        $recentWasteTransactions = InventoryTransaction::where('restaurant_id', $restaurantId)
            ->where('type', 'waste')
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['ingredient:id,name,unit_id', 'ingredient.unit', 'performedBy:id,name'])
            ->latest('occurred_at')
            ->take(50)
            ->get();

        $recentWasteApprovals = ApprovalRequest::where('restaurant_id', $restaurantId)
            ->where('operation_type', 'inventory_waste')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query->whereIn('status', ['pending', 'escalated'])
                    ->orWhere(fn ($rejected) => $rejected
                        ->where('status', 'rejected')
                        ->whereBetween('created_at', [$periodStart, $periodEnd]));
            })
            ->with(['requester:id,name'])
            ->latest('created_at')
            ->take(50)
            ->get();

        $pendingApprovalCount = ApprovalRequest::where('restaurant_id', $restaurantId)
            ->where('operation_type', 'inventory_waste')
            ->whereIn('status', ['pending', 'escalated'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $rejectedCount = ApprovalRequest::where('restaurant_id', $restaurantId)
            ->where('operation_type', 'inventory_waste')
            ->where('status', 'rejected')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $recentWastes = collect();

        $wasteTransactionIds = $recentWasteTransactions->pluck('id');
        $salaryAdjustmentMap = SalaryAdjustment::whereIn('reference_id', $wasteTransactionIds)
            ->where('reference_type', InventoryTransaction::class)
            ->with('employee:id,full_name')
            ->get()
            ->keyBy('reference_id');

        foreach ($recentWasteTransactions as $t) {
            $salaryAdjustment = $salaryAdjustmentMap->get($t->id);

            $recentWastes->push([
                'id' => $t->id,
                'is_approval' => false,
                'ingredient_name' => $t->ingredient?->name ?? '—',
                'quantity' => (float) $t->quantity,
                'unit_symbol' => $t->ingredient?->unit?->symbol ?? '—',
                'cost' => (float) $t->total_cost,
                'notes' => $t->notes,
                'performed_by' => $t->performedBy?->name ?? 'Hệ thống',
                'employee_name' => $salaryAdjustment?->employee?->full_name ?? 'Không khấu trừ',
                'waste_category' => $t->waste_category,
                'timestamp' => $t->occurred_at->timestamp,
                'occurred_at' => $t->occurred_at->format('d/m/Y H:i'),
                'status' => 'approved',
                'rejection_reason' => null,
            ]);
        }

        $pendingApprovals = $recentWasteApprovals->filter(fn ($r) => $r->status !== 'approved');
        $approvalIngredientIds = $pendingApprovals->map(fn ($r) => $r->operation_data['ingredient_id'] ?? null)->filter()->unique()->values();
        $approvalEmployeeIds = $pendingApprovals->map(fn ($r) => $r->operation_data['employee_id'] ?? null)->filter()->unique()->values();

        $approvalIngredients = Ingredient::whereIn('id', $approvalIngredientIds)->with('unit')->get()->keyBy('id');
        $approvalEmployees = Employee::whereIn('id', $approvalEmployeeIds)->get(['id', 'full_name'])->keyBy('id');

        foreach ($pendingApprovals as $r) {
            $opData = $r->operation_data;
            $ing = $approvalIngredients->get($opData['ingredient_id'] ?? null);
            $emp = ! empty($opData['employee_id']) ? $approvalEmployees->get($opData['employee_id']) : null;

            $recentWastes->push([
                'id' => $r->id,
                'is_approval' => true,
                'ingredient_name' => $opData['ingredient_name'] ?? ($ing?->name ?? '—'),
                'quantity' => (float) ($opData['quantity'] ?? 0),
                'unit_symbol' => $opData['unit_symbol'] ?? ($ing?->unit?->symbol ?? '—'),
                'cost' => (float) ($opData['estimated_cost'] ?? 0),
                'notes' => $opData['notes'] ?? null,
                'performed_by' => $r->requester?->name ?? 'Nhân viên',
                'employee_name' => $emp ? $emp->full_name : 'Không khấu trừ',
                'waste_category' => $opData['waste_category'] ?? null,
                'timestamp' => $r->created_at->timestamp,
                'occurred_at' => $r->created_at->format('d/m/Y H:i'),
                'status' => $r->status,
                'rejection_reason' => $r->rejection_reason,
            ]);
        }

        $recentWastes = $recentWastes->sortByDesc('timestamp')->values()->take(50);

        return Inertia::render('waste-management/Index', [
            'dashboard' => $dashboard,
            'trend' => $trend,
            'suggestions' => $suggestions,
            'expiring' => $expiring,
            'ingredients' => $ingredients,
            'employees' => $employees,
            'recentWastes' => $recentWastes,
            'days' => $days,
            'currentDate' => now()->format('d/m/Y'),
            'period' => [
                'from' => $periodStart->format('d/m/Y H:i'),
                'to' => $periodEnd->format('d/m/Y H:i'),
            ],
            'scopeLabel' => $scopeLabel,
            'historySummary' => [
                'pending' => $pendingApprovalCount,
                'approved' => (int) ($dashboard['waste_count'] ?? 0),
                'rejected' => $rejectedCount,
            ],
            'branchContext' => [
                'scope' => $tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
    }

    public function apiDashboard(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json(
            $this->analytics->getDashboard($request->user()->restaurant_id, $days, app(TenantContext::class)->activeBranchId())
        );
    }

    public function apiTrend(Request $request): JsonResponse
    {
        $months = (int) ($request->months ?? 6);

        return response()->json(
            $this->analytics->getTrendData($request->user()->restaurant_id, $months, app(TenantContext::class)->activeBranchId())
        );
    }

    public function apiSuggestions(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) ($request->days ?? 30)));

        return response()->json(
            $this->analytics->getAiSuggestions($request->user()->restaurant_id, app(TenantContext::class)->activeBranchId(), $days)
        );
    }

    public function apiExpiring(Request $request): JsonResponse
    {
        $days = (int) ($request->days ?? 3);

        return response()->json(
            $this->analytics->getExpiringItems($request->user()->restaurant_id, $days, app(TenantContext::class)->activeBranchId())
        );
    }
}
