<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BonusController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'warehouse_manager', 'super_admin']), 403);

        $restaurant = $user->restaurant;
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_full')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_full',
                'feature_label' => 'Thưởng nhân viên',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn phí',
                'required_plan' => 'Chuyên nghiệp',
            ]);
        }

        $tenantContext = app(TenantContext::class);
        $branchId = $tenantContext->activeBranchId();
        $branchFilter = fn ($query) => $tenantContext->isBranchScoped() && $branchId
            ? $query->where('branch_id', $branchId)
            : $query;

        $bonuses = EmployeeBonus::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->tap($branchFilter)
            ->with([
                'employee:id,full_name,employee_code,branch_id',
                'employee.branch:id,name',
                'awardedBy:id,name',
            ])
            ->latest('awarded_at')
            ->latest('id')
            ->limit(300)
            ->get()
            ->map(fn (EmployeeBonus $bonus) => [
                'id' => $bonus->id,
                'employee_id' => $bonus->employee_id,
                'employee_name' => $bonus->employee?->full_name,
                'employee_code' => $bonus->employee?->employee_code,
                'branch_name' => $bonus->employee?->branch?->name,
                'amount' => (float) $bonus->amount,
                'reason' => $bonus->reason,
                'awarded_at' => $bonus->awarded_at?->toDateString(),
                'awarded_by_name' => $bonus->awardedBy?->name,
                'status' => $bonus->status,
            ])
            ->values();

        $employees = Employee::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->tap($branchFilter)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'branch_id']);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthQuery = EmployeeBonus::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereBetween('awarded_at', [$monthStart, $monthEnd]);
        $branchFilter($monthQuery);

        return Inertia::render('bonuses/Index', [
            'bonuses' => $bonuses,
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'branch_id' => $employee->branch_id,
            ])->values(),
            'summary' => [
                'this_month_amount' => (float) $monthQuery->sum('amount'),
                'this_month_count' => (int) $monthQuery->count(),
                'active_employees' => (int) $employees->count(),
            ],
            'activeBranchId' => $branchId,
        ]);
    }
}
