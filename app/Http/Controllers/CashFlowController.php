<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\WorkShift;
use App\Services\QuotaService;
use App\Support\MaterializedViews\MaterializedViewReader;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __construct(
        private MaterializedViewReader $mvReader,
        private TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['cashier', 'manager', 'owner', 'accountant', 'super_admin']) || $user->hasPermissionTo('cashflow.view'), 403, 'Bạn không có quyền truy cập thông tin dòng tiền.');

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Dòng tiền',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        $isAllBranches = $this->tenantContext->isAllBranches();

        // Load every active register in the current scope. In the all-branch
        // scope this must be aggregated instead of taking the first register.
        $activeRegisters = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'open')
            ->with(['openedBy', 'shift', 'branch:id,name'])
            ->get();

        $activeRegistersForView = $isAllBranches
            ? $activeRegisters
            : $activeRegisters->take(1);
        $activeRegister = $this->serializeActiveRegister($activeRegistersForView, $isAllBranches);

        // Recent cash registers
        $registers = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['openedBy', 'closedBy', 'shift', 'branch:id,name'])
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'closing_date' => $r->closing_date->format('d/m/Y'),
                'branch_id' => $r->branch_id,
                'branch_name' => $r->branch?->name ?? '—',
                'shift_name' => $r->shift?->name ?? '—',
                'opened_by_name' => $r->openedBy?->name ?? '—',
                'closed_by_name' => $r->closedBy?->name ?? '—',
                'opening_balance' => (float) $r->opening_balance,
                'closing_balance' => (float) $r->closing_balance,
                'expected_closing_balance' => (float) $r->expected_closing_balance,
                'difference' => (float) $r->difference,
                'expense_budget' => (float) $r->expense_budget,
                'status' => $r->status,
                'opened_at' => $r->opened_at->format('H:i d/m/Y'),
                'closed_at' => $r->closed_at?->format('H:i d/m/Y'),
                'notes' => $r->notes,
            ]);

        // Transactions for the active register
        $activeTransactions = [];
        $activeRegisterIds = $activeRegistersForView->pluck('id');
        if ($activeRegisterIds->isNotEmpty()) {
            $activeTransactions = CashTransaction::whereIn('cash_register_id', $activeRegisterIds)
                ->with(['createdBy', 'branch:id,name'])
                ->latest('id')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'branch_id' => $t->branch_id,
                    'branch_name' => $t->branch?->name ?? '—',
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'source' => $t->source,
                    'notes' => $t->notes,
                    'created_by_name' => $t->createdBy?->name ?? '—',
                    'occurred_at' => $t->occurred_at->format('H:i d/m/Y'),
                ]);
        }

        // Cash flow charts: last 30 days daily cash movements — materialized
        // (xem CashFlowChartBuilder), tự fallback về live query nếu chưa có
        // dòng tổng hợp hoặc đã cũ.
        $cashFlowRollup = $this->mvReader->read('cash_flow_30d', $restaurantId, $branchId);
        $chartData = $cashFlowRollup['chart_data'];

        // Active work shifts for opening register selection
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get(['id', 'name', 'code']);

        // Cash Flow Forecast calculation
        $forecast = $this->calculateCashForecast($restaurantId, $branchId, $activeRegistersForView, $cashFlowRollup);

        return Inertia::render('cash-flow/Index', [
            'isAllBranches' => $isAllBranches,
            'activeRegister' => $activeRegister,
            'activeTransactions' => $activeTransactions,
            'registers' => $registers,
            'chartData' => $chartData,
            'shifts' => $shifts,
            'forecast' => $forecast,
        ]);
    }

    public function openRegister(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['cashier', 'manager', 'owner']), 403, 'Chỉ thu ngân, quản lý hoặc chủ nhà hàng mới có quyền mở két tiền.');

        $restaurantId = $user->restaurant_id;
        $branchId = $this->requireActiveBranch($user);

        $data = $request->validate([
            'shift_id' => ['required', 'integer', TenantRule::exists('work_shifts')],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'expense_budget' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Check if cashier already has an open register
        $closingDate = today();
        $exists = CashRegister::where('restaurant_id', $restaurantId)
            ->where('cashier_user_id', $user->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return back()->withErrors(['shift_id' => 'Bạn đã có một két tiền mặt đang mở. Vui lòng đóng két cũ trước khi mở két mới.']);
        }

        CashRegister::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'shift_id' => $data['shift_id'],
            'closing_date' => $closingDate,
            'opened_by' => $user->id,
            'cashier_user_id' => $user->id,
            'opening_balance' => $data['opening_balance'],
            'expense_budget' => $data['expense_budget'] ?? 0,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => $data['notes'],
        ]);

        return redirect()->route('cash-flow.index')->with('success', 'Đã mở két đầu ca thành công.');
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['cashier', 'manager', 'owner']), 403, 'Chỉ thu ngân, quản lý hoặc chủ nhà hàng mới có quyền ghi nhận giao dịch tiền mặt.');

        $restaurantId = $user->restaurant_id;
        $branchId = $this->requireActiveBranch($user);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['required', 'string', 'max:500'],
            'source' => ['required', 'string', 'in:expense,other'],
            'voucher_code' => ['nullable', 'string', 'max:100'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        $activeRegister = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'open')
            ->first();

        if (! $activeRegister) {
            return back()->withErrors(['amount' => 'Chưa có két tiền nào được mở. Vui lòng mở két tiền mặt trước khi tạo giao dịch.']);
        }

        // Check receipt / voucher requirement for out/expense transactions
        if ($data['type'] === 'out' && empty($data['voucher_code']) && empty($data['notes'])) {
            return back()->withErrors(['voucher_code' => 'Khoản chi tiền mặt phải có chứng từ hoặc ghi chú chi tiết.']);
        }

        // Budget overrun enforcement for cash out/expenses
        if ($data['type'] === 'out' && $activeRegister->expense_budget > 0) {
            $existingOut = (float) CashTransaction::where('cash_register_id', $activeRegister->id)
                ->where('type', 'out')
                ->sum('amount');
            if ($existingOut + $data['amount'] > $activeRegister->expense_budget) {
                if (! $user->isOwner() && ! $user->isSuperAdmin()) {
                    return back()->withErrors(['amount' => 'Khoản chi vượt quá ngân sách ca (tối đa '.number_format($activeRegister->expense_budget).'đ). Yêu cầu phê duyệt từ Quản lý hoặc Chủ nhà hàng.']);
                }
                $data['notes'] .= ' [Đã phê duyệt vượt ngân sách]';
            }
        }

        $notes = $data['notes'];
        if (! empty($data['voucher_code'])) {
            $notes .= ' [Mã chứng từ: '.$data['voucher_code'].']';
        }

        CashTransaction::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'cash_register_id' => $activeRegister->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'source' => $data['source'],
            'notes' => $notes,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);

        $msg = $data['type'] === 'out' ? 'Ghi nhận khoản chi tiền mặt thành công.' : 'Ghi nhận khoản thu tiền mặt thành công.';

        return redirect()->route('cash-flow.index')->with('success', $msg);
    }

    public function reversalTransaction(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403, 'Chỉ Chủ doanh nghiệp mới được đảo giao dịch tiền mặt.');
        abort_if($transaction->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->requireActiveBranch($user);

        if ($transaction->source === 'reversal') {
            return back()->withErrors(['transaction' => 'Không thể tạo giao dịch đảo cho một giao dịch đảo khác.']);
        }

        $oppositeType = $transaction->type === 'in' ? 'out' : 'in';

        CashTransaction::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'cash_register_id' => $transaction->cash_register_id,
            'type' => $oppositeType,
            'amount' => $transaction->amount,
            'source' => 'reversal',
            'notes' => 'Đảo giao dịch #'.$transaction->id.': '.$transaction->notes,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);

        return redirect()->route('cash-flow.index')->with('success', 'Đã tạo giao dịch đảo thành công.');
    }

    public function getForecast(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        if (! $restaurantId) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $branchId = $this->tenantContext->activeBranchId();

        $activeRegisters = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'open')
            ->get();

        $cashFlowRollup = $this->mvReader->read('cash_flow_30d', $restaurantId, $branchId);

        return response()->json($this->calculateCashForecast($restaurantId, $branchId, $activeRegisters, $cashFlowRollup));
    }

    private function serializeActiveRegister(Collection $registers, bool $isAllBranches): ?array
    {
        if ($registers->isEmpty()) {
            return null;
        }

        if (! $isAllBranches) {
            $register = $registers->first();

            return [
                'id' => $register->id,
                'branch_id' => $register->branch_id,
                'branch_name' => $register->branch?->name ?? '—',
                'opening_balance' => (float) $register->opening_balance,
                'expense_budget' => (float) $register->expense_budget,
                'opened_at' => $register->opened_at->format('H:i d/m/Y'),
                'opened_by_name' => $register->openedBy?->name ?? '—',
                'shift_name' => $register->shift?->name ?? '—',
                'closing_date' => $register->closing_date->toDateString(),
                'expected_cash' => $this->calculateLiveExpectedCash($register->id),
                'is_aggregate' => false,
                'register_count' => 1,
            ];
        }

        $firstRegister = $registers->sortBy('opened_at')->first();
        $registerCount = $registers->count();

        return [
            'id' => null,
            'branch_id' => null,
            'branch_name' => 'Toàn chuỗi',
            'opening_balance' => (float) $registers->sum('opening_balance'),
            'expense_budget' => (float) $registers->sum('expense_budget'),
            'opened_at' => $registerCount === 1
                ? $firstRegister->opened_at->format('H:i d/m/Y')
                : 'Nhiều thời điểm',
            'opened_by_name' => $registerCount === 1
                ? ($firstRegister->openedBy?->name ?? '—')
                : "{$registerCount} két đang mở",
            'shift_name' => $registerCount === 1
                ? ($firstRegister->shift?->name ?? '—')
                : 'Nhiều ca',
            'closing_date' => today()->toDateString(),
            'expected_cash' => (float) $registers->sum(
                fn ($register) => $this->calculateLiveExpectedCash($register->id),
            ),
            'is_aggregate' => true,
            'register_count' => $registerCount,
        ];
    }

    private function calculateLiveExpectedCash(int $registerId): float
    {
        $register = CashRegister::findOrFail($registerId);

        $in = (float) CashTransaction::where('cash_register_id', $registerId)
            ->where('type', 'in')
            ->sum('amount');

        $out = (float) CashTransaction::where('cash_register_id', $registerId)
            ->where('type', 'out')
            ->sum('amount');

        return (float) $register->opening_balance + $in - $out;
    }

    /**
     * @param  array{avg_daily_in: float, avg_daily_out: float}  $cashFlowRollup  Từ
     *                                                                            materialized view 'cash_flow_30d' (CashFlowChartBuilder) — trung bình thu/chi
     *                                                                            30 ngày, KHÔNG tính lại ở đây nữa. current_cash / projected values / status /
     *                                                                            message vẫn tính live vì phụ thuộc két tiền đang mở — xem CashFlowChartBuilder.
     */
    private function calculateCashForecast(int $restaurantId, ?int $branchId, Collection $activeRegisters, array $cashFlowRollup): array
    {
        $avgDailyIn = (float) $cashFlowRollup['avg_daily_in'];
        $avgDailyOut = (float) $cashFlowRollup['avg_daily_out'];

        // In the all-branch scope, use the current value for every branch:
        // active registers use their live balance; branches without an open
        // register use their latest closed balance.
        $currentCash = 0;
        if ($this->tenantContext->isAllBranches()) {
            $currentCash = (float) $activeRegisters->sum(
                fn ($register) => $this->calculateLiveExpectedCash($register->id),
            );

            $lastClosedRegisters = CashRegister::where('restaurant_id', $restaurantId)
                ->where('status', 'closed')
                ->latest('closed_at')
                ->get();

            $activeBranchIds = $activeRegisters->pluck('branch_id')
                ->filter()
                ->unique();

            $currentCash += (float) $lastClosedRegisters
                ->unique('branch_id')
                ->reject(fn ($register) => $activeBranchIds->contains($register->branch_id))
                ->sum('closing_balance');
        } elseif ($activeRegisters->isNotEmpty()) {
            $currentCash = (float) $activeRegisters->sum(
                fn ($register) => $this->calculateLiveExpectedCash($register->id),
            );
        } else {
            // If no register is open, use the latest closed register. The
            // branch scope has already limited this query to one branch.
            $lastClosed = CashRegister::where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', 'closed')
                ->latest('closed_at')
                ->first();

            if ($lastClosed) {
                $currentCash = (float) $lastClosed->closing_balance;
            }
        }

        // Forecast for the next 7 days
        $projectedIn = $avgDailyIn * 7;
        $projectedOut = $avgDailyOut * 7;
        $projectedBalance = $currentCash + $projectedIn - $projectedOut;

        $status = 'safe';
        $message = 'Khả năng thanh toán tiền mặt an toàn cho 7 ngày tới.';

        if ($projectedBalance < 0) {
            $status = 'warning';
            $message = 'Cảnh báo: Dòng tiền mặt có thể thiếu hụt trong 7 ngày tới. Dự kiến thâm hụt khoảng '.number_format(abs($projectedBalance)).'đ. Vui lòng tăng dự trữ tiền mặt hoặc giảm bớt các khoản chi ngoài hệ thống.';
        } elseif ($currentCash < $avgDailyOut * 2) {
            $status = 'low_reserve';
            $message = 'Lưu ý: Quỹ tiền mặt hiện tại khá thấp, chỉ đủ chi tiêu trong chưa đầy 2 ngày tới.';
        }

        return [
            'avg_daily_in' => $avgDailyIn,
            'avg_daily_out' => $avgDailyOut,
            'current_cash' => $currentCash,
            'projected_in' => $projectedIn,
            'projected_out' => $projectedOut,
            'projected_balance' => $projectedBalance,
            'status' => $status,
            'message' => $message,
        ];
    }

    private function requireActiveBranch($user): int
    {
        $branchId = $this->tenantContext->activeBranchId()
            ?? ($user->isOwner() ? $user->assignedBranchId() : null);
        abort_if($branchId === null, 422, 'Hãy chọn chi nhánh hiện tại trước khi ghi nhận nghiệp vụ két tiền.');
        abort_unless($user->canAccessBranch($branchId), 403);

        return $branchId;
    }
}
