<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\ApprovalRequest;
use App\Models\WorkShift;
use App\Services\CashPostingService;
use App\Services\QuotaService;
use App\Support\MaterializedViews\MaterializedViewReader;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __construct(
        private MaterializedViewReader $mvReader,
        private TenantContext $tenantContext,
        private CashPostingService $cashPostingService,
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
            ->with(['openedBy', 'shift', 'branch:id,name', 'area:id,name,code'])
            ->get();

        // A branch may have one independent register per cashier area. Keep
        // every active register in the view; the summary card aggregates them
        // while transaction endpoints always require a concrete area when it
        // is ambiguous.
        $activeRegistersForView = $activeRegisters;
        $activeRegister = $this->serializeActiveRegister($activeRegistersForView, $isAllBranches);

        $areas = $this->availableAreas($restaurantId, $branchId)
            ->map(fn (Area $area) => [
                'id' => $area->id,
                'name' => $area->name,
                'code' => $area->code,
            ])
            ->values();

        $activeRegisterRows = $activeRegisters->map(fn (CashRegister $register) => [
            'id' => $register->id,
            'area_id' => $register->area_id,
            'area_name' => $register->area?->name ?? 'Mặc định / Mang về',
            'requires_opening_reconciliation' => (bool) $register->requires_opening_reconciliation,
        ])->values();

        // Recent cash registers
        $registers = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['openedBy', 'closedBy', 'shift', 'branch:id,name', 'area:id,name,code'])
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'closing_date' => $r->closing_date->format('d/m/Y'),
                'branch_id' => $r->branch_id,
                'branch_name' => $r->branch?->name ?? '—',
                'area_id' => $r->area_id,
                'area_name' => $r->area?->name ?? 'Mặc định / Mang về',
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
                'auto_opened' => (bool) $r->auto_opened,
                'requires_opening_reconciliation' => (bool) $r->requires_opening_reconciliation,
            ]);

        // Transactions for the active register
        $activeTransactions = [];
        $activeRegisterIds = $activeRegistersForView->pluck('id');
        if ($activeRegisterIds->isNotEmpty()) {
            $activeTransactions = CashTransaction::whereIn('cash_register_id', $activeRegisterIds)
                ->with(['createdBy', 'branch:id,name', 'register.area:id,name'])
                ->latest('id')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'branch_id' => $t->branch_id,
                    'branch_name' => $t->branch?->name ?? '—',
                    'area_name' => $t->register?->area?->name ?? 'Mặc định / Mang về',
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

        // Active work shifts for opening register selection (scoped to current branch)
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get(['id', 'name', 'code', 'start_time', 'end_time'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'start_time' => $s->start_time ? substr($s->start_time, 0, 5) : null,
                'end_time' => $s->end_time ? substr($s->end_time, 0, 5) : null,
            ]);

        // Cash Flow Forecast calculation
        $forecast = $this->calculateCashForecast($restaurantId, $branchId, $activeRegistersForView, $cashFlowRollup);

        return Inertia::render('cash-flow/Index', [
            'isAllBranches' => $isAllBranches,
            'isManager' => $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']) || $user->hasPermissionTo('cashflow.manage'),
            'activeRegister' => $activeRegister,
            'activeTransactions' => $activeTransactions,
            'registers' => $registers,
            'chartData' => $chartData,
            'shifts' => $shifts,
            'areas' => $areas,
            'activeRegisters' => $activeRegisterRows,
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
            'area_id' => ['nullable', 'integer'],
            'opening_balance' => ['required', 'numeric', 'decimal:0', 'min:0'],
            'expense_budget' => ['nullable', 'numeric', 'decimal:0', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereKey($data['shift_id'])
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->first();

        if (! $shift) {
            return back()->withErrors([
                'shift_id' => 'Ca làm việc không thuộc chi nhánh hiện tại hoặc đã ngừng hoạt động.',
            ]);
        }

        try {
            $areaId = $this->resolveAreaId($restaurantId, $branchId, $data['area_id'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        // A cashier may own only one open register, but a branch may have
        // several open registers as long as each one belongs to a different
        // area.
        $closingDate = today();
        $exists = CashRegister::where('restaurant_id', $restaurantId)
            ->where('status', 'open')
            ->where('cashier_user_id', $user->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['shift_id' => 'Bạn đã có một két tiền mặt đang mở. Vui lòng đóng két cũ trước khi mở két mới.']);
        }

        try {
            $register = DB::transaction(function () use ($data, $restaurantId, $branchId, $areaId, $user, $closingDate): CashRegister {
                $openRegisters = CashRegister::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('status', 'open')
                    ->where('area_id', $areaId)
                    ->lockForUpdate()
                    ->get();

                if ($openRegisters->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'area_id' => 'Khu vực này đã có két đang mở. Hãy chọn khu vực khác hoặc đóng két hiện tại.',
                        'shift_id' => 'Khu vực này đã có két đang mở. Hãy chọn khu vực khác hoặc đóng két hiện tại.',
                    ]);
                }

                $register = CashRegister::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'shift_id' => $data['shift_id'],
                    'closing_date' => $closingDate,
                    'opened_by' => $user->id,
                    'cashier_user_id' => $user->id,
                    'area_id' => $areaId,
                    'opening_balance' => $data['opening_balance'],
                    'expected_closing_balance' => $data['opening_balance'],
                    'expense_budget' => $data['expense_budget'] ?? 0,
                    'open_scope_key' => $this->openScopeKey($restaurantId, $branchId, $areaId),
                    'status' => 'open',
                    'opened_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                AuditLog::log('cash_register_opened', 'created', $register, null, [
                    'branch_id' => $branchId,
                    'area_id' => $areaId,
                    'shift_id' => $data['shift_id'],
                    'opening_balance' => (float) $data['opening_balance'],
                    'expense_budget' => (float) ($data['expense_budget'] ?? 0),
                ]);

                return $register;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'cash_registers_open_scope_unique')) {
                return back()->withErrors([
                    'shift_id' => 'Két của chi nhánh vừa được mở bởi request khác. Vui lòng tải lại trang.',
                ]);
            }

            throw $exception;
        }

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
            'amount' => ['required', 'numeric', 'decimal:0', 'min:1'],
            'area_id' => ['nullable', 'integer'],
            'notes' => ['required', 'string', 'max:500'],
            'source' => ['required', 'string', 'in:expense,other'],
            'voucher_code' => [
                'nullable',
                'required_if:type,out',
                'string',
                'min:3',
                'max:100',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            ],
            'idempotency_key' => ['nullable', 'string', 'max:160'],
            // 'is_approved' đã bị bỏ — không có cơ chế phê duyệt thực sự ở field này.
        ]);

        $data['notes'] = trim($data['notes']);
        $data['voucher_code'] = filled($data['voucher_code'] ?? null)
            ? strtoupper(trim($data['voucher_code']))
            : null;

        if ($data['notes'] === '') {
            return back()->withErrors(['notes' => 'Nội dung giao dịch không được để trống.']);
        }

        $expectedSource = $data['type'] === 'out' ? 'expense' : 'other';
        if ($data['source'] !== $expectedSource) {
            return back()->withErrors([
                'source' => $data['type'] === 'out'
                    ? 'Khoản chi tiền mặt phải dùng nguồn Chi phí.'
                    : 'Khoản thu thủ công phải dùng nguồn Khác.',
            ]);
        }

        try {
            $areaId = $this->resolveAreaId($restaurantId, $branchId, $data['area_id'] ?? null);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $activeRegisters = CashRegister::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('area_id', $areaId)
            ->where('status', 'open')
            ->get();

        if ($activeRegisters->isEmpty()) {
            return back()->withErrors(['area_id' => 'Khu vực này chưa có két tiền đang mở. Vui lòng mở két đúng khu vực trước khi tạo giao dịch.']);
        }

        if ($activeRegisters->count() > 1) {
            return back()->withErrors([
                'area_id' => 'Phát hiện nhiều két trong cùng khu vực. Đã khóa ghi nhận để tránh giao dịch vào nhầm két.',
            ]);
        }

        $activeRegister = $activeRegisters->first();

        if ($activeRegister->requires_opening_reconciliation && $data['type'] === 'out') {
            return back()->withErrors([
                'amount' => 'Két này được hệ thống tự mở do ca chưa khai báo số dư đầu ca. Cần quản lý đối soát số dư trước khi thực hiện khoản chi.',
            ]);
        }

        $rawIdempotencyKey = trim((string) ($data['idempotency_key'] ?? $request->header('Idempotency-Key', '')));
        if ($rawIdempotencyKey === '') {
            $idempotencyKey = 'fingerprint:'.hash('sha256', implode('|', [
                $user->id,
                $activeRegister->id,
                $data['type'],
                (string) $data['amount'],
                $data['source'],
                $data['voucher_code'] ?? '',
                $data['notes'],
            ]));
        } else {
            $idempotencyKey = Str::limit(preg_replace('/[^A-Za-z0-9:_-]/', '-', $rawIdempotencyKey), 150, '');
        }

        if ($data['voucher_code'] && CashTransaction::where('restaurant_id', $restaurantId)
            ->where('voucher_code', $data['voucher_code'])
            ->where(function ($query) use ($idempotencyKey): void {
                $query->whereNull('idempotency_key')
                    ->orWhere('idempotency_key', '!=', 'manual-cash:'.$idempotencyKey);
            })
            ->exists()) {
            return back()->withErrors(['voucher_code' => 'Mã chứng từ này đã được ghi nhận trước đó, không thể dùng lại.']);
        }

        // ── [SECURITY P0] Kiểm soát ngân sách chi tiền mặt ──────────────────────
        // expense_budget > 0 mới là hạn mức đã được cấu hình. Giá trị 0 giữ
        // tương thích với các két cũ chưa có ngân sách và không tự biến thành
        // một lỗi chặn giao dịch; khi đã cấu hình thì mọi khoản chi đều bị
        // kiểm soát theo hạn mức và được audit đầy đủ.
        if ($data['type'] === 'out') {
            $budget = (float) $activeRegister->expense_budget;

            if ($budget > 0) {
                $existingOut = (float) CashTransaction::where('cash_register_id', $activeRegister->id)
                    ->where('type', 'out')
                    ->sum('amount');

                if ($existingOut + $data['amount'] > $budget) {
                    if ($user->isOwner() || $user->isSuperAdmin()) {
                        $data['notes'] .= ' [Owner đã phê duyệt vượt ngân sách ca: '.number_format($existingOut + $data['amount'] - $budget).'đ]';
                    } else {
                        $data['notes'] .= ' [Khoản chi vượt ngân sách ca; chờ Chủ phê duyệt]';
                    }
                }
            }
        }

        // Cashier/manager submissions are recorded as approval requests. The
        // actual cash movement and journal entry are created only by the
        // ApprovalService after an owner decision.
        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            $currentCash = (float) $activeRegister->opening_balance
                + (float) CashTransaction::where('cash_register_id', $activeRegister->id)->where('type', 'in')->sum('amount')
                - (float) CashTransaction::where('cash_register_id', $activeRegister->id)->where('type', 'out')->sum('amount');
            if ($data['type'] === 'out' && $currentCash + 0.01 < (float) $data['amount']) {
                return back()->withErrors(['amount' => 'Số dư két hiện tại không đủ cho khoản chi này.']);
            }

            $existingApproval = ApprovalRequest::where('restaurant_id', $restaurantId)
                ->where('operation_type', 'cash_manual_transaction')
                ->open()
                ->whereJsonContains('operation_data->idempotency_key', $idempotencyKey)
                ->first();
            if ($existingApproval) {
                return redirect()->route('cash-flow.index')->with('success', 'Giao dịch này đã được gửi và đang chờ phê duyệt.');
            }

            app(\App\Services\ApprovalService::class)->submitRequest('cash_manual_transaction', [
                'restaurant_id' => $restaurantId,
                'branch_id' => $branchId,
                'cash_register_id' => $activeRegister->id,
                'area_id' => $areaId,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'source' => $data['source'],
                'voucher_code' => $data['voucher_code'],
                'idempotency_key' => $idempotencyKey,
                'budget_limit' => (float) $activeRegister->expense_budget,
                'notes' => $data['notes'],
                'created_by' => $user->id,
                'occurred_at' => now()->toDateTimeString(),
                'allow_budget_overrun' => true,
            ], $user);

            return redirect()->route('cash-flow.index')->with('success', 'Đã gửi giao dịch tiền mặt chờ Chủ phê duyệt.');
        }

        $notes = $data['notes'];

        $isExpense = $data['type'] === 'out' || $data['source'] === 'expense';
        $transaction = $this->cashPostingService->record([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'cash_register_id' => $activeRegister->id,
            'area_id' => $areaId,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'source' => $data['source'],
            'idempotency_key' => 'manual-cash:'.$idempotencyKey,
            'voucher_code' => $data['voucher_code'],
            'enforce_cash_balance' => true,
            'budget_limit' => (float) $activeRegister->expense_budget,
            'allow_budget_overrun' => $user->isOwner() || $user->isSuperAdmin(),
            'debit_account' => $isExpense ? '6271' : '1111',
            'credit_account' => $isExpense ? '1111' : '5112',
            'notes' => $notes,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);

        if ($transaction?->wasRecentlyCreated) {
            AuditLog::log('cash_transaction_posted', 'created', $transaction, null, [
                'cash_register_id' => $activeRegister->id,
                'type' => $data['type'],
                'amount' => (float) $data['amount'],
                'source' => $data['source'],
                'voucher_code' => $data['voucher_code'],
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        $msg = $data['type'] === 'out' ? 'Ghi nhận khoản chi tiền mặt thành công.' : 'Ghi nhận khoản thu tiền mặt thành công.';

        return redirect()->route('cash-flow.index')->with('success', $msg);
    }

    public function reversalTransaction(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403, 'Chỉ Chủ doanh nghiệp mới được đảo giao dịch tiền mặt.');
        abort_if($transaction->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->requireActiveBranch($user);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $reason = trim($data['reason']);

        abort_if((int) $transaction->branch_id !== (int) $branchId, 403, 'Không thể đảo giao dịch của chi nhánh khác.');

        if (CashTransaction::where('restaurant_id', $user->restaurant_id)
            ->where('reversal_of_id', $transaction->id)
            ->exists()) {
            return back()->withErrors(['transaction' => 'Giao dịch này đã được đảo trước đó.']);
        }

        if ($transaction->source === 'reversal' || $transaction->reversal_of_id !== null) {
            return back()->withErrors(['transaction' => 'Không thể tạo giao dịch đảo cho một giao dịch đảo khác.']);
        }

        $oppositeType = $transaction->type === 'in' ? 'out' : 'in';
        $register = CashRegister::where('restaurant_id', $user->restaurant_id)
            ->where('branch_id', $branchId)
            ->whereKey($transaction->cash_register_id)
            ->where('status', 'open')
            ->first();
        abort_unless($register, 422, 'Phải mở két tiền mặt hiện tại trước khi đảo giao dịch.');

        $isOrderIncome = $transaction->source === 'order';
        $isRefund = $transaction->source === 'refund';
        $reversal = $this->cashPostingService->record([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'cash_register_id' => $register->id,
            'area_id' => $register->area_id,
            'type' => $oppositeType,
            'amount' => $transaction->amount,
            'source' => 'reversal',
            'reversal_of_id' => $transaction->id,
            'idempotency_key' => 'cash-reversal:'.$transaction->id,
            'enforce_cash_balance' => $oppositeType === 'out',
            'debit_account' => $oppositeType === 'in'
                ? '1111'
                : ($isOrderIncome ? '5211' : '5112'),
            'credit_account' => $oppositeType === 'in'
                ? ($isRefund ? '5211' : '6271')
                : '1111',
            'reference_id' => $transaction->id,
            'reference_type' => CashTransaction::class,
            'journal_source_type' => CashTransaction::class,
            'journal_source_id' => $transaction->id,
            'notes' => 'Đảo giao dịch #'.$transaction->id.': '.$transaction->notes,
            'created_by' => $user->id,
            'occurred_at' => now(),
        ]);

        if ($reversal?->wasRecentlyCreated) {
            AuditLog::log('cash_transaction_reversed', 'created', $reversal, null, [
                'reversal_of_id' => $transaction->id,
                'reason' => $reason,
                'cash_register_id' => $register->id,
            ]);
        }

        return redirect()->route('cash-flow.index')->with('success', 'Đã tạo giao dịch đảo thành công.');
    }

    public function reconcileOpening(Request $request, CashRegister $register): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->hasRole('manager') || $user->isSuperAdmin(), 403, 'Chỉ quản lý hoặc chủ nhà hàng mới được đối soát két tự mở.');
        abort_if($register->restaurant_id !== $user->restaurant_id, 403);

        $branchId = $this->requireActiveBranch($user);
        abort_if((int) $register->branch_id !== $branchId, 403, 'Không thể đối soát két của chi nhánh khác.');

        $data = $request->validate([
            'opening_balance' => ['required', 'numeric', 'decimal:0', 'min:0'],
            'notes' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        DB::transaction(function () use ($register, $data): void {
            $lockedRegister = CashRegister::withoutGlobalScopes()
                ->whereKey($register->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRegister->requires_opening_reconciliation) {
                throw ValidationException::withMessages([
                    'opening_balance' => 'Két này không còn ở trạng thái chờ đối soát.',
                ]);
            }

            $netPostedCash = (float) CashTransaction::withoutGlobalScopes()
                ->where('cash_register_id', $lockedRegister->id)
                ->where('type', 'in')
                ->sum('amount')
                - (float) CashTransaction::withoutGlobalScopes()
                    ->where('cash_register_id', $lockedRegister->id)
                    ->where('type', 'out')
                    ->sum('amount');

            $openingBalance = (float) $data['opening_balance'];
            $lockedRegister->update([
                'opening_balance' => $openingBalance,
                'expected_closing_balance' => round($openingBalance + $netPostedCash, 2),
                'requires_opening_reconciliation' => false,
                'notes' => trim(($lockedRegister->notes ? $lockedRegister->notes."\n" : '').'[Đã đối soát số dư đầu ca] '.$data['notes']),
            ]);

            AuditLog::log('cash_register_opening_reconciled', 'updated', $lockedRegister, null, [
                'opening_balance' => $openingBalance,
                'notes' => $data['notes'],
                'auto_opened' => (bool) $lockedRegister->auto_opened,
            ]);
        });

        return redirect()->route('cash-flow.index')->with('success', 'Đã đối soát số dư đầu ca thành công.');
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

        if (! $isAllBranches && $registers->count() === 1) {
            $register = $registers->first();

            return [
                'id' => $register->id,
                'branch_id' => $register->branch_id,
                'branch_name' => $register->branch?->name ?? '—',
                'area_id' => $register->area_id,
                'area_name' => $register->area?->name ?? 'Mặc định / Mang về',
                'opening_balance' => (float) $register->opening_balance,
                'expense_budget' => (float) $register->expense_budget,
                'opened_at' => $register->opened_at->format('H:i d/m/Y'),
                'opened_by_name' => $register->openedBy?->name ?? '—',
                'shift_name' => $register->shift?->name ?? '—',
                'closing_date' => $register->closing_date->toDateString(),
                'expected_cash' => $this->calculateLiveExpectedCash($register->id),
                'is_aggregate' => false,
                'register_count' => 1,
                'needs_opening_reconciliation' => (bool) $register->requires_opening_reconciliation,
            ];
        }

        $firstRegister = $registers->sortBy('opened_at')->first();
        $registerCount = $registers->count();

        return [
            'id' => null,
            'branch_id' => $isAllBranches ? null : $firstRegister->branch_id,
            'branch_name' => $isAllBranches ? 'Toàn chuỗi' : ($firstRegister->branch?->name ?? '—'),
            'area_id' => null,
            'area_name' => $isAllBranches ? 'Nhiều chi nhánh' : 'Nhiều khu vực',
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
            'needs_opening_reconciliation' => $registers->contains(
                fn (CashRegister $register): bool => (bool) $register->requires_opening_reconciliation,
            ),
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

    private function availableAreas(int $restaurantId, ?int $branchId): Collection
    {
        return Area::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId !== null, fn ($query) => $query->where(function ($scope) use ($branchId): void {
                $scope->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function resolveAreaId(int $restaurantId, int $branchId, mixed $requestedAreaId): ?int
    {
        if ($requestedAreaId !== null && $requestedAreaId !== '') {
            $area = $this->availableAreas($restaurantId, $branchId)
                ->firstWhere('id', (int) $requestedAreaId);

            if (! $area) {
                throw ValidationException::withMessages([
                    'area_id' => 'Khu vực không thuộc chi nhánh hiện tại hoặc đã ngừng hoạt động.',
                ]);
            }

            return (int) $area->id;
        }

        $areas = $this->availableAreas($restaurantId, $branchId);
        if ($areas->count() > 1) {
            throw ValidationException::withMessages([
                'area_id' => 'Chi nhánh có nhiều khu vực thu ngân. Vui lòng chọn đúng khu vực.',
            ]);
        }

        return $areas->first()?->id;
    }

    private function openScopeKey(int $restaurantId, int $branchId, ?int $areaId): string
    {
        return "{$restaurantId}:{$branchId}:".($areaId ?? 'default');
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
