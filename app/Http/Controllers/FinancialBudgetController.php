<?php

namespace App\Http\Controllers;

use App\Models\FinancialBudget;
use App\Models\FinancialAccount;
use App\Models\RestaurantBranch;
use App\Services\FinancialBudgetService;
use App\Support\TenantRule;
use App\Support\Tenant\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialBudgetController extends Controller
{
    public function __construct(private FinancialBudgetService $budgetService, private TenantContext $tenantContext) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $user = $request->user();
        $budgets = FinancialBudget::where('restaurant_id', $user->restaurant_id)
            ->with('lines')
            ->latest('period_start')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (FinancialBudget $budget): array => $this->budgetService->serialize($budget));

        return Inertia::render('financial-budgets/Index', [
            'budgets' => $budgets,
            'branches' => RestaurantBranch::where('restaurant_id', $user->restaurant_id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'budgetAccounts' => $this->budgetAccountsForRestaurant((int) $user->restaurant_id),
            'canApprove' => $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'branch_id' => ['nullable', TenantRule::exists('restaurant_branches')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.period_month' => ['required', 'date_format:Y-m'],
            'lines.*.account_code' => ['required', 'string', Rule::in(array_column($this->budgetAccountsForRestaurant((int) $request->user()->restaurant_id), 'code'))],
            'lines.*.category_id' => ['nullable', TenantRule::exists('expense_categories')],
            'lines.*.budget_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $startMonth = CarbonImmutable::parse($data['period_start'])->format('Y-m');
        $endMonth = CarbonImmutable::parse($data['period_end'])->format('Y-m');
        foreach ($data['lines'] as $index => $line) {
            if ($line['period_month'] < $startMonth || $line['period_month'] > $endMonth) {
                throw ValidationException::withMessages([
                    "lines.{$index}.period_month" => 'Tháng của khoản mục phải nằm trong kỳ ngân sách đã chọn.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $data): void {
            $total = collect($data['lines'])->sum(fn ($line) => (float) $line['budget_amount']);
            $budget = FinancialBudget::create([
                'restaurant_id' => $request->user()->restaurant_id,
                'branch_id' => $data['branch_id'] ?? null,
                'name' => $data['name'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => 'draft',
                'total_amount' => $total,
                'created_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($data['lines'] as $line) {
                $budget->lines()->create([
                    'restaurant_id' => $budget->restaurant_id,
                    'period_month' => CarbonImmutable::createFromFormat('Y-m-d', $line['period_month'].'-01')->startOfMonth()->toDateString(),
                    'account_code' => $line['account_code'],
                    'category_id' => $line['category_id'] ?? null,
                    'budget_amount' => $line['budget_amount'],
                ]);
            }
        });

        return back()->with('success', 'Đã tạo ngân sách tài chính ở trạng thái nháp.');
    }

    public function approve(Request $request, FinancialBudget $budget): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403, 'Chỉ Chủ doanh nghiệp mới được duyệt ngân sách.');
        abort_if($budget->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($budget->status === 'draft', 422, 'Chỉ ngân sách nháp mới được duyệt.');
        $budget->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

        return back()->with('success', 'Đã duyệt ngân sách.');
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']) || $request->user()->hasPermissionTo('finance.view'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin() || $request->user()->hasRole('accountant'), 403);
    }

    /**
     * Keep the standard aliases used by existing budgets, then add tenant
     * expense accounts so a custom chart of accounts remains supported.
     *
     * @return array<int, array{code: string, name: string, actual_basis: string}>
     */
    private function budgetAccountsForRestaurant(int $restaurantId): array
    {
        $standard = collect($this->budgetService->budgetAccountOptions())->keyBy('code');
        $custom = FinancialAccount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('type', 'expense')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (FinancialAccount $account): array => [
                $account->code => [
                    'code' => $account->code,
                    'name' => $account->name,
                    'actual_basis' => 'Phiếu chi phí đã duyệt hoặc đã trả trong tháng.',
                ],
            ]);

        return $standard->union($custom)->values()->all();
    }
}
