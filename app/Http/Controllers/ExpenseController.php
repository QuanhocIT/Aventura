<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\OperatingExpense;
use App\Models\RecurringExpense;
use App\Services\ProfitLossService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * Display a listing of the resources.
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $user = $request->user();

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Chi phí',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        // 1. Fetch Categories (includes system-shared ones)
        $categories = ExpenseCategory::all();

        // 2. Build Expenses Query with Filters
        $query = OperatingExpense::with(['category', 'creator', 'branch'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        $categoryId = $request->input('category_id');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $expenses = $query->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // 3. Fetch Recurring Expenses
        $recurringExpenses = RecurringExpense::with(['category', 'branch'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->latest()
            ->get();

        // 4. Calculate Analytics (This Month vs Last Month)
        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth()->toDateString();
        $thisMonthEnd = $now->copy()->endOfMonth()->toDateString();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // Total sum
        $totalThisMonth = (float) OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('amount');
        $totalLastMonth = (float) OperatingExpense::whereBetween('expense_date', [$lastMonthStart, $lastMonthEnd])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('amount');

        // MoM Delta
        $momDelta = 0.0;
        if ($totalLastMonth > 0) {
            $momDelta = round((($totalThisMonth - $totalLastMonth) / $totalLastMonth) * 100, 1);
        } elseif ($totalThisMonth > 0) {
            $momDelta = 100.0;
        }

        // Recurring share ratio
        $recurringTotalThisMonth = (float) OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])
            ->whereNotNull('recurring_expense_id')
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->sum('amount');
        $recurringRatio = $totalThisMonth > 0 ? round(($recurringTotalThisMonth / $totalThisMonth) * 100, 1) : 0.0;

        // Six months MoM data
        $sixMonthsMom = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $mStart = $monthDate->copy()->startOfMonth()->toDateString();
            $mEnd = $monthDate->copy()->endOfMonth()->toDateString();
            $mAmount = (float) OperatingExpense::whereBetween('expense_date', [$mStart, $mEnd])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->sum('amount');
            $sixMonthsMom[] = [
                'month' => $monthDate->format('m/Y'),
                'label' => 'Tháng '.$monthDate->format('m/Y'),
                'amount' => $mAmount,
            ];
        }

        // Category breakdown this month
        $rawBreakdown = OperatingExpense::whereBetween('expense_date', [$thisMonthStart, $thisMonthEnd])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->select('category_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category_id')
            ->get();

        $categoryBreakdown = [];
        foreach ($rawBreakdown as $row) {
            $category = $categories->firstWhere('id', $row->category_id);
            $catName = $category ? $category->name : 'Chưa phân loại';
            $categoryBreakdown[] = [
                'id' => $row->category_id,
                'name' => $catName,
                'amount' => (float) $row->total_amount,
                'percentage' => $totalThisMonth > 0 ? round(($row->total_amount / $totalThisMonth) * 100, 1) : 0.0,
            ];
        }
        usort($categoryBreakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $year = (int) ($request->input('year') ?? now()->year);
        $month = (int) ($request->input('month') ?? now()->month);
        $profitLossService = app(ProfitLossService::class);

        $analytics = [
            'total_this_month' => $totalThisMonth,
            'total_last_month' => $totalLastMonth,
            'mom_delta' => $momDelta,
            'recurring_ratio' => $recurringRatio,
            'six_months_mom' => $sixMonthsMom,
            'category_breakdown' => $categoryBreakdown,
        ];

        // ── Hạn mức chi tiêu chi nhánh ───────────────────────────────────────────
        $budgetService = app(\App\Services\ExpenseBudgetService::class);
        $budgetMonth = \Illuminate\Support\Carbon::create((int) $year, (int) $month, 1);
        $isOwner = $user->hasRole('owner') || $user->isSuperAdmin();

        $expenseBudget = null;
        if ($branchId) {
            $b = $budgetService->budgetFor($restaurantId, $branchId, $budgetMonth);
            $expenseBudget = [
                'has_budget' => (bool) $b,
                'budget_amount' => $b ? (float) $b->budget_amount : null,
                'require_receipt' => $b ? (bool) $b->require_receipt : false,
                'committed' => $budgetService->committedThisMonth($restaurantId, $branchId, $budgetMonth),
                'remaining' => $budgetService->remaining($restaurantId, $branchId, $budgetMonth),
                'month' => $budgetMonth->format('m/Y'),
            ];
        }

        // Chủ: danh sách hạn mức mọi chi nhánh trong tháng để quản lý.
        $branchBudgets = [];
        if ($isOwner && $restaurant) {
            foreach ($restaurant->branches()->get(['id', 'name']) as $br) {
                $b = $budgetService->budgetFor($restaurantId, $br->id, $budgetMonth);
                $branchBudgets[] = [
                    'branch_id' => $br->id,
                    'branch_name' => $br->name,
                    'budget_amount' => $b ? (float) $b->budget_amount : null,
                    'require_receipt' => $b ? (bool) $b->require_receipt : true,
                    'committed' => $budgetService->committedThisMonth($restaurantId, $br->id, $budgetMonth),
                    'remaining' => $budgetService->remaining($restaurantId, $br->id, $budgetMonth),
                ];
            }
        }

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'recurringExpenses' => $recurringExpenses,
            'categories' => $categories,
            'analytics' => $analytics,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'category_id' => $categoryId,
                'year' => $year,
                'month' => $month,
                'branch_id' => $branchId,
            ],
            'profitLossReport' => Inertia::defer(fn () => $profitLossService->buildWithComparison($restaurantId, $year, $month, $branchId)),
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
            'expenseBudget' => $expenseBudget,
            'branchBudgets' => $branchBudgets,
            'canManageBudget' => $isOwner,
        ]);
    }

    /**
     * Chủ đặt/cập nhật hạn mức chi tiêu tháng cho một chi nhánh.
     */
    public function storeBranchBudget(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('owner') || $user->isSuperAdmin(), 403, 'Chỉ Chủ được đặt hạn mức chi tiêu.');

        $data = $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'require_receipt' => ['sometimes', 'boolean'],
            'effective_month' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $month = \Illuminate\Support\Carbon::parse($data['effective_month'] ?? now())->startOfMonth();

        \App\Models\BranchExpenseBudget::updateOrCreate(
            [
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $data['branch_id'],
                'effective_month' => $month->toDateString(),
            ],
            [
                'budget_amount' => $data['budget_amount'],
                'require_receipt' => (bool) ($data['require_receipt'] ?? true),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]
        );

        return back()->with('success', 'Đã lưu hạn mức chi tiêu chi nhánh tháng '.$month->format('m/Y').'.');
    }

    /**
     * Store a manual operating expense.
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'category_id' => ['nullable', TenantRule::exists('expense_categories')],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'invoice' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'], // Max 5MB
        ]);

        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        $isOwner = $user->hasRole('owner') || $user->isSuperAdmin();

        // ── Hạn mức chi tiêu chi nhánh (do Chủ đặt) ──────────────────────────────
        // Quản lý: (1) BẮT BUỘC hoá đơn nếu hạn mức yêu cầu; (2) không được vượt hạn mức.
        // Chủ: được phép vượt (tự chịu trách nhiệm) nhưng vẫn hiển thị cảnh báo ở UI.
        $budgetService = app(\App\Services\ExpenseBudgetService::class);
        $month = \Illuminate\Support\Carbon::parse($data['expense_date']);
        $budget = $budgetService->budgetFor($restaurantId, $branchId, $month);

        if (! $isOwner && $budget) {
            if ($budget->require_receipt && ! $request->hasFile('invoice')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'invoice' => 'Chi nhánh này yêu cầu ĐÍNH KÈM HOÁ ĐƠN cho mọi khoản chi.',
                ]);
            }
            if (! $budgetService->canFit($restaurantId, $branchId, (float) $data['amount'], $month)) {
                $remaining = max(0.0, (float) $budgetService->remaining($restaurantId, $branchId, $month));
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Vượt hạn mức chi tiêu chi nhánh tháng '.$month->format('m/Y').'. Còn lại '
                        .number_format($remaining).'đ, khoản này '.number_format((float) $data['amount']).'đ. Đề nghị Chủ tăng hạn mức.',
                ]);
            }
        }

        $invoicePath = null;
        if ($request->hasFile('invoice')) {
            $path = $request->file('invoice')->store('invoices', 'public');
            $invoicePath = '/storage/'.$path;
        }

        OperatingExpense::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'branch_id' => $this->tenantContext->activeBranchId(),
            'category_id' => $data['category_id'] ?? null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'description' => $data['description'] ?? null,
            'invoice_path' => $invoicePath,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã thêm chi phí vận hành mới thành công.');
    }

    /**
     * Update an operating expense.
     */
    public function update(Request $request, OperatingExpense $expense)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($expense->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);

        if (in_array($expense->status, ['approved', 'paid'])) {
            abort(403, 'Chi phí đã được phê duyệt hoặc thanh toán không thể sửa trực tiếp. Hãy tạo chứng từ đảo hoặc điều chỉnh.');
        }

        $data = $request->validate([
            'category_id' => ['nullable', TenantRule::exists('expense_categories')],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'invoice' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        if ($request->hasFile('invoice')) {
            if ($expense->invoice_path && str_starts_with($expense->invoice_path, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $expense->invoice_path);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('invoice')->store('invoices', 'local');
            $data['invoice_path'] = $path;
        }

        unset($data['invoice']);

        $expense->update($data);

        return back()->with('success', 'Đã cập nhật thông tin chi phí.');
    }

    public function destroy(Request $request, OperatingExpense $expense)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($expense->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);

        if (in_array($expense->status, ['approved', 'paid'])) {
            abort(403, 'Chi phí đã được phê duyệt hoặc thanh toán không thể xóa trực tiếp. Hãy tạo chứng từ đảo hoặc điều chỉnh.');
        }

        if ($expense->invoice_path && str_starts_with($expense->invoice_path, '/storage/')) {
            $oldPath = str_replace('/storage/', '', $expense->invoice_path);
            Storage::disk('public')->delete($oldPath);
        }

        $expense->delete();

        return back()->with('success', 'Đã xóa khoản chi phí thành công.');
    }

    public function approveExpense(Request $request, OperatingExpense $expense)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($expense->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);

        $expense->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã phê duyệt chứng từ chi phí thành công.');
    }

    /**
     * Create a recurring expense configuration.
     */
    public function storeRecurring(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'category_id' => ['required', TenantRule::exists('expense_categories')],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        RecurringExpense::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'branch_id' => $this->tenantContext->activeBranchId(),
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'amount' => $data['amount'],
            'frequency' => $data['frequency'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Đã tạo cấu hình chi phí định kỳ mới.');
    }

    /**
     * Update recurring expense configuration.
     */
    public function updateRecurring(Request $request, RecurringExpense $recurring)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($recurring->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch($recurring->branch_id), 403);

        $data = $request->validate([
            'category_id' => ['sometimes', 'required', TenantRule::exists('expense_categories')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'frequency' => ['sometimes', 'required', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $recurring->update($data);

        return back()->with('success', 'Đã cập nhật cấu hình chi phí định kỳ.');
    }

    /**
     * Delete recurring expense configuration.
     */
    public function destroyRecurring(Request $request, RecurringExpense $recurring)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($recurring->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($request->user()->canAccessBranch($recurring->branch_id), 403);

        $recurring->delete();

        return back()->with('success', 'Đã xóa cấu hình chi phí định kỳ.');
    }

    /**
     * Add a custom expense category.
     */
    public function storeCategory(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        ExpenseCategory::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('success', 'Đã thêm danh mục chi phí tùy chỉnh mới.');
    }

    /**
     * Delete a custom expense category.
     */
    public function destroyCategory(Request $request, ExpenseCategory $category)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        // Only allow deleting custom categories belonging to this restaurant
        abort_if($category->restaurant_id !== $request->user()->restaurant_id, 403);

        $category->delete();

        return back()->with('success', 'Đã xóa danh mục chi phí tùy chỉnh.');
    }
}
