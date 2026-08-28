<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\FinancialAccount;
use App\Models\FinancialJournalEntry;
use App\Models\FinancialJournalLine;
use App\Services\FinancialPostingService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private FinancialPostingService $postingService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin'])
                || $user->hasPermissionTo('finance.view'),
            403,
        );

        $restaurantId = (int) $user->restaurant_id;
        $this->postingService->ensureDefaultChart($restaurantId);
        $periodValue = $request->validate(['period' => ['nullable', 'date_format:Y-m']])['period'] ?? today()->format('Y-m');
        $periodDate = CarbonImmutable::createFromFormat('Y-m-d', $periodValue.'-01');
        $period = AccountingPeriod::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('period_start', $periodDate->startOfMonth()->toDateString())
            ->whereDate('period_end', $periodDate->endOfMonth()->toDateString())
            ->first();
        if (! $period) {
            $period = AccountingPeriod::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId,
                'period_start' => $periodDate->startOfMonth()->toDateString(),
                'period_end' => $periodDate->endOfMonth()->toDateString(),
                'status' => 'open',
            ]);
        }

        $branchId = $this->tenantContext->activeBranchId();
        $entries = FinancialJournalEntry::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('accounting_period_id', $period->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with(['lines.account:id,code,name,type', 'branch:id,name', 'createdBy:id,name'])
            ->latest('entry_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (FinancialJournalEntry $entry): array => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_date' => $entry->entry_date?->format('Y-m-d'),
                'description' => $entry->description,
                'status' => $entry->status,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'reversal_of_id' => $entry->reversal_of_id,
                'branch_name' => $entry->branch?->name,
                'created_by' => $entry->createdBy?->name,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
                'lines' => $entry->lines->map(fn ($line): array => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])->values()->all(),
            ]);

        $trialBalance = FinancialJournalLine::withoutGlobalScopes()
            ->join('financial_journal_entries as entries', 'entries.id', '=', 'financial_journal_lines.journal_entry_id')
            ->join('financial_accounts as accounts', 'accounts.id', '=', 'financial_journal_lines.financial_account_id')
            ->where('financial_journal_lines.restaurant_id', $restaurantId)
            ->where('entries.accounting_period_id', $period->id)
            ->where('entries.status', 'posted')
            ->when($branchId !== null, fn ($query) => $query->where('financial_journal_lines.branch_id', $branchId))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(financial_journal_lines.debit) as debit'),
                DB::raw('SUM(financial_journal_lines.credit) as credit'),
            ])
            ->map(fn ($row): array => [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
                'balance' => round((float) $row->debit - (float) $row->credit, 2),
            ])->values();

        // Balance-sheet and cash figures are point-in-time balances. Include
        // all posted periods up to the selected period; the trial balance
        // above remains the selected month's movement report.
        $cumulativeBalance = FinancialJournalLine::withoutGlobalScopes()
            ->join('financial_journal_entries as entries', 'entries.id', '=', 'financial_journal_lines.journal_entry_id')
            ->join('financial_accounts as accounts', 'accounts.id', '=', 'financial_journal_lines.financial_account_id')
            ->where('financial_journal_lines.restaurant_id', $restaurantId)
            ->whereDate('entries.entry_date', '<=', $period->period_end->toDateString())
            ->where('entries.status', 'posted')
            ->when($branchId !== null, fn ($query) => $query->where('financial_journal_lines.branch_id', $branchId))
            ->groupBy('accounts.type')
            ->get([
                'accounts.type',
                DB::raw('SUM(financial_journal_lines.debit - financial_journal_lines.credit) as balance'),
            ])
            ->pluck('balance', 'type')
            ->map(fn ($value): float => (float) $value);

        $closeChecklist = $this->postingService->closeChecklist($period);

        $periods = AccountingPeriod::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->latest('period_start')
            ->limit(18)
            ->get(['id', 'period_start', 'period_end', 'status', 'closed_at'])
            ->map(fn (AccountingPeriod $item): array => [
                'id' => $item->id,
                'period' => $item->period_start->format('Y-m'),
                'status' => $item->status,
                'closed_at' => $item->closed_at?->format('Y-m-d H:i'),
            ])->values();

        $accounts = FinancialAccount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance', 'is_system', 'is_active'])
            ->map(fn (FinancialAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'is_system' => (bool) $account->is_system,
                'is_active' => (bool) $account->is_active,
            ])->values();

        $balanceRows = $trialBalance->groupBy('type')->map(fn ($rows): float => round((float) $rows->sum('balance'), 2));
        $revenueBalance = (float) ($balanceRows['revenue'] ?? 0);
        $expenseBalance = (float) ($balanceRows['expense'] ?? 0);
        $netProfit = round(-$revenueBalance - $expenseBalance, 2);
        $cumulativeNetProfit = round(-((float) ($cumulativeBalance['revenue'] ?? 0)) - ((float) ($cumulativeBalance['expense'] ?? 0)), 2);
        $statements = [
            'income_statement' => [
                'revenue' => round(abs($revenueBalance), 2),
                'expense' => round(abs($expenseBalance), 2),
                'net_profit' => $netProfit,
            ],
            'balance_sheet' => [
                'assets' => round(abs((float) ($cumulativeBalance['asset'] ?? 0)), 2),
                'liabilities' => round(abs((float) ($cumulativeBalance['liability'] ?? 0)), 2),
                // Revenue/expense accounts are not closed to retained
                // earnings until year-end, so include cumulative net result.
                'equity' => round(abs((float) ($cumulativeBalance['equity'] ?? 0)) + $cumulativeNetProfit, 2),
            ],
            'cash_position' => round(abs((float) FinancialJournalLine::withoutGlobalScopes()
                ->join('financial_journal_entries as entries', 'entries.id', '=', 'financial_journal_lines.journal_entry_id')
                ->join('financial_accounts as accounts', 'accounts.id', '=', 'financial_journal_lines.financial_account_id')
                ->where('financial_journal_lines.restaurant_id', $restaurantId)
                ->whereDate('entries.entry_date', '<=', $period->period_end->toDateString())
                ->where('entries.status', 'posted')
                ->whereIn('accounts.code', ['1111', '1121', '1122', '1123'])
                ->when($branchId !== null, fn ($query) => $query->where('financial_journal_lines.branch_id', $branchId))
                ->sum(DB::raw('financial_journal_lines.debit - financial_journal_lines.credit'))), 2),
        ];

        return Inertia::render('finance/Index', [
            'period' => [
                'id' => $period->id,
                'period' => $periodDate->format('Y-m'),
                'status' => $period->status,
                'period_start' => $period->period_start->format('Y-m-d'),
                'period_end' => $period->period_end->format('Y-m-d'),
            ],
            'entries' => $entries,
            'trialBalance' => $trialBalance,
            'periods' => $periods,
            'closeChecklist' => $closeChecklist,
            'accounts' => $accounts,
            'statements' => $statements,
            'canReverse' => $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('accountant') || $user->hasPermissionTo('finance.manage'),
            'canManageAccounts' => $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('accountant') || $user->hasPermissionTo('finance.manage'),
            'canClose' => $user->isOwner() || $user->isSuperAdmin(),
            'canReopen' => $user->isOwner() || $user->isSuperAdmin(),
            'summary' => [
                'total_debit' => round((float) $trialBalance->sum('debit'), 2),
                'total_credit' => round((float) $trialBalance->sum('credit'), 2),
                'entry_count' => $entries->total(),
            ],
        ]);
    }

    public function closePeriod(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($period->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $this->postingService->closePeriod($period, $user, $data['notes'] ?? null);

        return back()->with('success', 'Đã khóa kỳ tài chính. Mọi điều chỉnh sau đó phải dùng bút toán đảo.');
    }

    public function closeChecklist(Request $request, AccountingPeriod $period): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin'])
                || $user->hasPermissionTo('finance.view'),
            403,
        );
        abort_if($period->restaurant_id !== $user->restaurant_id, 403);

        return response()->json([
            'period' => $period->id,
            'checklist' => $this->postingService->closeChecklist($period),
        ]);
    }

    public function reopenPeriod(Request $request, AccountingPeriod $period): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($period->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->postingService->reopenPeriod($period, $user, $data['reason']);

        return back()->with('success', 'Đã mở lại kỳ tài chính theo yêu cầu kiểm soát.');
    }

    public function storeEntry(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('accountant')
                || $user->hasPermissionTo('finance.manage'),
            403,
        );
        $this->postingService->ensureDefaultChart((int) $user->restaurant_id);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'branch_id' => ['nullable', TenantRule::exists('restaurant_branches')],
            'description' => ['required', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:180'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account' => ['required', 'string', 'max:30'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:100'],
        ]);

        if ($this->tenantContext->isBranchScoped()) {
            $this->tenantContext->assertWriteBranch((int) ($data['branch_id'] ?? $this->tenantContext->activeBranchId()));
        }

        try {
            $this->postingService->post([
                'restaurant_id' => $user->restaurant_id,
                'branch_id' => $data['branch_id'] ?? $this->tenantContext->activeBranchId(),
                'entry_date' => $data['entry_date'],
                'source_type' => 'manual_adjustment',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'description' => $data['description'],
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'metadata' => ['manual_adjustment' => true],
                'lines' => $data['lines'],
            ]);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận bút toán điều chỉnh.');
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('accountant')
                || $user->hasPermissionTo('finance.manage'),
            403,
        );

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('financial_accounts', 'code')->where('restaurant_id', $user->restaurant_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'parent_id' => ['nullable', TenantRule::exists('financial_accounts')],
        ]);

        FinancialAccount::create($data + [
            'restaurant_id' => $user->restaurant_id,
            'is_system' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã thêm tài khoản kế toán.');
    }

    public function reverseEntry(Request $request, FinancialJournalEntry $entry): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('accountant')
                || $user->hasPermissionTo('finance.manage'),
            403,
        );
        abort_if($entry->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($entry->status === 'posted', 422, 'Chỉ bút toán đã ghi sổ mới được đảo.');
        abort_if($entry->reversal_of_id !== null, 422, 'Không thể đảo lại một bút toán đảo.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->postingService->reverse($entry, $user, $data['reason']);

        return back()->with('success', 'Đã tạo bút toán đảo trong kỳ đang mở.');
    }
}
