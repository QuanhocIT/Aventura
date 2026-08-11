<?php

namespace App\Http\Controllers;

use App\Models\BranchPayrollBudget;
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
            $amount = $budget ? (float) $budget->budget_amount : 0.0;

            return [
                'branch_id' => $b->id,
                'branch_name' => $b->name,
                'budget_amount' => $amount,
                'committed' => $committed,
                'remaining' => $budget ? $amount - $committed : null,
                'over_budget' => $budget ? $committed > $amount : false,
            ];
        });

        return Inertia::render('payroll-budget/Index', [
            'month' => $month->format('m/Y'),
            'branches' => $rows,
            'wageTiers' => WageTier::where('restaurant_id', $restaurantId)
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'compensation_type', 'rate', 'is_active']),
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
            'branch_id' => ['nullable', 'integer', "exists:restaurant_branches,id,restaurant_id,{$user->restaurant_id}"],
        ]);

        WageTier::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $data['branch_id'] ?? null,
            'name' => $data['name'],
            'compensation_type' => $data['compensation_type'],
            'rate' => $data['rate'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã thêm bậc lương.');
    }

    public function destroyWageTier(Request $request, WageTier $wageTier): RedirectResponse
    {
        $this->authorizeOwner($request);
        $wageTier->delete();

        return back()->with('success', 'Đã xoá bậc lương.');
    }

    private function authorizeOwner(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner') || $user->hasRole('super_admin'), 403,
            'Chỉ Chủ doanh nghiệp được quản lý quỹ lương.');

        return $user;
    }
}
