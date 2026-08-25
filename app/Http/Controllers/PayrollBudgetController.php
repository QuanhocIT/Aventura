<?php

namespace App\Http\Controllers;

use App\Models\BranchPayrollBudget;
use App\Models\Employee;
use App\Models\RestaurantBranch;
use App\Models\WageTier;
use App\Services\PayrollBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quỹ lương & bậc lương theo chi nhánh — CHỈ Chủ doanh nghiệp. Quản lý không được
 * đặt quỹ/bậc lương (đây là quyền quyết định ngân sách của chủ).
 */
class PayrollBudgetController extends Controller
{
    public function __construct(private readonly PayrollBudgetService $budgets) {}

    public function index(Request $request): Response
    {
        $user = $this->authorizeOwner($request);
        $restaurantId = $user->restaurant_id;
        $month = Carbon::now()->startOfMonth();

        $branches = RestaurantBranch::where('restaurant_id', $restaurantId)
            ->orderBy('name')->get(['id', 'name', 'code']);

        $budgets = BranchPayrollBudget::where('restaurant_id', $restaurantId)
            ->whereDate('effective_month', $month->toDateString())
            ->get()->keyBy('branch_id');

        $rows = $branches->map(function (RestaurantBranch $b) use ($restaurantId, $budgets) {
            $budget = $budgets->get($b->id);
            $committed = $this->budgets->committedMonthlyWages($restaurantId, $b->id);
            $amount = $budget ? (float) $budget->budget_amount : null;

            return [
                'branch_id' => $b->id,
                'branch_name' => $b->name,
                'branch_code' => $b->code,
                'budget_amount' => $amount,
                'committed' => $committed,
                'remaining' => $budget ? $amount - $committed : null,
                'over_budget' => $budget ? $committed > $amount : false,
                'notes' => $budget?->notes,
            ];
        });

        return Inertia::render('payroll-budget/Index', [
            'month' => $month->format('m/Y'),
            'branches' => $rows,
            'wageTiers' => WageTier::where('restaurant_id', $restaurantId)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'compensation_type', 'rate', 'revenue_percent', 'is_active']),
            'payrollRules' => [
                'hours_per_month' => WageTier::HOURS_PER_MONTH,
                'shifts_per_month' => WageTier::SHIFTS_PER_MONTH,
            ],
        ]);
    }

    public function storeBudget(Request $request): RedirectResponse
    {
        $user = $this->authorizeOwner($request);
        $data = $request->validate([
            'branch_id' => ['required', 'integer', "exists:restaurant_branches,id,restaurant_id,{$user->restaurant_id}"],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $committed = $this->budgets->committedMonthlyWages($user->restaurant_id, (int) $data['branch_id']);
        if ((float) $data['budget_amount'] + 0.01 < $committed) {
            return back()->withErrors([
                'budget_amount' => 'Quỹ lương không được thấp hơn tổng lương nhân viên đang hoạt động: '.number_format($committed).'đ.',
            ]);
        }

        BranchPayrollBudget::updateOrCreate(
            [
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $data['branch_id'],
                'effective_month' => Carbon::now()->startOfMonth()->toDateString(),
            ],
            [
                'budget_amount' => $data['budget_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]
        );

        return back()->with('success', 'Đã cập nhật quỹ lương chi nhánh.');
    }

    public function storeWageTier(Request $request): RedirectResponse
    {
        $user = $this->authorizeOwner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'compensation_type' => ['required', 'in:hourly,shift,fixed'],
            'rate' => ['required', 'numeric', 'min:0'],
            'revenue_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'branch_id' => ['nullable', 'integer', "exists:restaurant_branches,id,restaurant_id,{$user->restaurant_id}"],
        ]);

        WageTier::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $data['branch_id'] ?? null,
            'name' => $data['name'],
            'compensation_type' => $data['compensation_type'],
            'rate' => $data['rate'],
            'revenue_percent' => array_key_exists('revenue_percent', $data) && $data['revenue_percent'] !== null && $data['revenue_percent'] !== '' ? $data['revenue_percent'] : null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã thêm bậc lương.');
    }

    public function destroyWageTier(Request $request, WageTier $wageTier): RedirectResponse
    {
        $user = $this->authorizeOwner($request);
        abort_unless((int) $wageTier->restaurant_id === (int) $user->restaurant_id, 404);
        $wageTier->delete();

        return back()->with('success', 'Đã xoá bậc lương.');
    }

    public function updateWageTier(Request $request, WageTier $wageTier): RedirectResponse
    {
        $user = $this->authorizeOwner($request);
        abort_unless((int) $wageTier->restaurant_id === (int) $user->restaurant_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'compensation_type' => ['required', 'in:hourly,shift,fixed'],
            'rate' => ['required', 'numeric', 'min:0'],
            'revenue_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'branch_id' => ['nullable', 'integer', "exists:restaurant_branches,id,restaurant_id,{$user->restaurant_id}"],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Kiểm tra xem cập nhật bậc lương có làm vượt quỹ chi nhánh không
        $newRate = (float) $data['rate'];
        $newType = $data['compensation_type'];
        $targetBranchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $wageTier->branch_id;

        $assignedEmployees = Employee::where('wage_tier_id', $wageTier->id)
            ->where('status', 'active')
            ->get();

        if ($assignedEmployees->isNotEmpty()) {
            $employeesByBranch = $assignedEmployees->groupBy('branch_id');
            foreach ($employeesByBranch as $bId => $emps) {
                $checkBranchId = $bId ? (int) $bId : ($targetBranchId ? (int) $targetBranchId : null);
                if (! $checkBranchId) {
                    continue;
                }
                $budget = $this->budgets->budgetFor($user->restaurant_id, $checkBranchId);
                if ($budget) {
                    $currentCommitted = $this->budgets->committedMonthlyWages($user->restaurant_id, $checkBranchId);
                    $oldWagesForTier = $emps->sum(fn ($e) => $this->budgets->monthlyWageOf($e));
                    $newWagesForTier = $emps->count() * $this->budgets->estimateMonthly($newType, $newRate, $newRate);
                    $diff = $newWagesForTier - $oldWagesForTier;
                    if ($currentCommitted + $diff > (float) $budget->budget_amount + 0.01) {
                        $remaining = max(0.0, (float) $budget->budget_amount - $currentCommitted);

                        return back()->withErrors([
                            'rate' => 'Tăng bậc lương này sẽ làm tổng lương chi nhánh vượt quỹ được cấp. Quỹ còn lại '.number_format($remaining).'đ.',
                        ]);
                    }
                }
            }
        }

        $wageTier->update($data);

        return back()->with('success', 'Đã cập nhật bậc lương.');
    }

    public function toggleWageTier(Request $request, WageTier $wageTier): RedirectResponse
    {
        $user = $this->authorizeOwner($request);
        abort_unless((int) $wageTier->restaurant_id === (int) $user->restaurant_id, 404);
        $wageTier->update(['is_active' => ! $wageTier->is_active]);

        return back()->with('success', $wageTier->is_active ? 'Đã kích hoạt bậc lương.' : 'Đã tạm dừng bậc lương.');
    }

    private function authorizeOwner(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner') || $user->hasRole('super_admin'), 403,
            'Chỉ Chủ doanh nghiệp được quản lý quỹ lương.');

        return $user;
    }
}
