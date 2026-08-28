<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CashCount;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantBranch;
use App\Models\RestaurantTable;
use App\Models\SalaryAdjustment;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\QuotaService;
use App\Services\SalaryService;
use App\Support\CashControlSettings;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShiftClosingController extends Controller
{
    /** Menh gia tien Viet Nam dang luu hanh. */
    private const DENOMINATIONS = [500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000, 500];

    public function __construct(
        private TenantContext $tenantContext,
        private InventoryService $inventoryService,
        private OrderService $orderService,
    ) {}

    /**
     * Chủ cấu hình kiểm soát tiền mặt cuối ca: bật đếm mù, ngưỡng giải trình/ảnh, bắt
     * buộc bàn giao 2 chữ ký. Áp toàn chuỗi (branch_id null) hoặc riêng một chi nhánh.
     */
    public function updateCashControl(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isOwner(), 403, 'Chỉ Chủ được cấu hình kiểm soát tiền mặt.');

        $data = $request->validate([
            'blind_cash_count_enabled' => ['required', 'boolean'],
            'cash_variance_threshold' => ['required', 'numeric', 'min:0'],
            'cash_evidence_threshold' => ['required', 'numeric', 'min:0'],
            'cash_handover_required' => ['required', 'boolean'],
            'branch_id' => ['nullable', TenantRule::exists('restaurant_branches')],
        ]);

        $branchId = $data['branch_id'] ?? null;
        $map = [
            CashControlSettings::BLIND_COUNT => (bool) $data['blind_cash_count_enabled'],
            CashControlSettings::VARIANCE_THRESHOLD => (float) $data['cash_variance_threshold'],
            CashControlSettings::EVIDENCE_THRESHOLD => (float) $data['cash_evidence_threshold'],
            CashControlSettings::HANDOVER_REQUIRED => (bool) $data['cash_handover_required'],
        ];

        foreach ($map as $key => $value) {
            DB::table('restaurant_settings')->updateOrInsert(
                ['restaurant_id' => $user->restaurant_id, 'branch_id' => $branchId, 'key_name' => $key],
                ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
            );
        }

        return back()->with('success', 'Đã lưu cấu hình kiểm soát tiền mặt cuối ca.');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']) || $user->hasPermissionTo('manage_salary'),
            403,
            'Bạn không có quyền truy cập trang Chốt ca.'
        );

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Chốt ca',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        $statusFilter = $request->input('status', 'all');
        $monthFilter = $request->input('month', today()->format('Y-m'));

        [$year, $month] = explode('-', $monthFilter);

        $query = ShiftClosing::where('restaurant_id', $restaurantId)
            ->with(['shift', 'cashier', 'confirmedBy'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereYear('closing_date', $year)
            ->whereMonth('closing_date', $month)
            ->latest('closing_date');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $closings = $query->get()->map(fn (ShiftClosing $c) => [
            'id' => $c->id,
            'closing_date' => $c->closing_date->format('d/m/Y'),
            'closing_date_raw' => $c->closing_date->toDateString(),
            'shift_name' => $c->shift?->name ?? '—',
            'shift_code' => $c->shift?->code ?? '',
            'shift_start' => $c->shift?->start_time ?? '',
            'shift_end' => $c->shift?->end_time ?? '',
            'period_start_at' => $c->period_start_at?->format('H:i d/m/Y'),
            'area_name' => $c->area_name ?? ($c->area?->name ?? 'Khu vực chung'),
            'order_count' => (int) $c->order_count,
            'total_order_count' => (int) ($c->total_order_count ?? $c->order_count),
            'cash_order_count' => (int) ($c->cash_order_count ?? 0),
            'transfer_order_count' => (int) ($c->transfer_order_count ?? 0),
            'cancelled_order_count' => (int) ($c->cancelled_order_count ?? 0),
            'cancelled_total_amount' => (float) ($c->cancelled_total_amount ?? 0),
            'refunded_order_count' => (int) ($c->refunded_order_count ?? 0),
            'refunded_total_amount' => (float) ($c->refunded_total_amount ?? 0),
            'cashier_name' => $c->cashier?->name ?? '—',
            'status' => $c->status,
            'expected_cash' => (float) $c->expected_cash,
            'cash_sales_amount' => (float) ($c->cash_sales_amount ?? 0),
            'actual_cash' => (float) $c->actual_cash,
            'cash_difference' => (float) $c->cash_difference,
            'transfer_amount' => (float) $c->transfer_amount,
            'actual_transfer_amount' => (float) ($c->actual_transfer_amount ?? 0),
            'transfer_difference' => (float) ($c->transfer_difference ?? 0),
            'gross_revenue_amount' => (float) ($c->gross_revenue_amount ?? 0),
            'discount_amount' => (float) ($c->discount_amount ?? 0),
            'net_revenue_amount' => (float) ($c->net_revenue_amount ?? 0),
            'total_difference' => (float) ($c->total_difference ?? $c->cash_difference),
            'responsibility_amount' => is_null($c->responsibility_amount)
                ? (float) $c->cash_difference
                : (float) $c->responsibility_amount,
            'responsibility_note' => $c->responsibility_note,
            'gross_revenue' => (float) ($c->gross_revenue_amount ?? ($c->actual_cash + $c->transfer_amount - ($c->refunded_total_amount ?? 0))),
            'other_expense' => (float) $c->other_expense_amount,
            'notes' => $c->notes,
            'confirmed_by_name' => $c->confirmedBy?->name ?? null,
            'closed_at' => $c->closed_at?->format('H:i d/m/Y'),
        ]);

        // KPI tổng tháng
        $kpi = [
            'total_closings' => $closings->count(),
            'total_gross' => $closings->sum('gross_revenue'),
            'total_cash' => $closings->sum('actual_cash'),
            'total_transfer' => $closings->sum('transfer_amount'),
            'total_difference' => $closings->sum('cash_difference'),
        ];

        // Auto-seed ca mặc định nếu chưa có
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get(['id', 'name', 'code', 'start_time', 'end_time', 'is_overnight']);

        if ($shifts->isEmpty() && $restaurantId) {
            foreach ([
                ['name' => 'Ca Sáng', 'code' => 'CA_SANG',  'start_time' => '06:00', 'end_time' => '14:00'],
                ['name' => 'Ca Chiều', 'code' => 'CA_CHIEU', 'start_time' => '14:00', 'end_time' => '22:00'],
                ['name' => 'Ca Tối',  'code' => 'CA_TOI',   'start_time' => '18:00', 'end_time' => '23:59'],
            ] as $ds) {
                WorkShift::withoutGlobalScopes()->firstOrCreate(
                    ['restaurant_id' => $restaurantId, 'code' => $ds['code']],
                    array_merge($ds, ['restaurant_id' => $restaurantId, 'branch_id' => $branchId, 'status' => 'active', 'is_overnight' => false])
                );
            }
            $shifts = WorkShift::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
                ->get(['id', 'name', 'code', 'start_time', 'end_time', 'is_overnight']);
        }

        $areas = Area::where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($sq) => $sq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->get(['id', 'name']);

        $isManager = $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']) || $user->hasPermissionTo('manage_salary');

        return Inertia::render('shift-closings/Index', [
            'closings' => $closings->values(),
            'shifts' => $shifts,
            'areas' => $areas,
            'kpi' => $kpi,
            'filters' => ['status' => $statusFilter, 'month' => $monthFilter],
            'activeBranchId' => $branchId,
            'branchScope' => $this->tenantContext->scopeKey(),
            'canConfirm' => $user->hasAnyRole(['owner', 'manager']),
            'isManager' => $isManager,
            // Cấu hình kiểm soát tiền mặt (Chủ chỉnh được).
            'isOwner' => $user->isOwner() || $user->isSuperAdmin(),
            'cashControl' => CashControlSettings::all($restaurantId, $branchId),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'shift_id' => ['required', 'integer'],
            'closing_date' => ['required', 'date'],
        ]);

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->resolveOperationalBranch($request->user());

        $shift = WorkShift::where('restaurant_id', $restaurantId)
            ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->findOrFail($request->integer('shift_id'));

        $closingDate = Carbon::parse($request->input('closing_date'));

        $closingAt = now();
        [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate, $closingAt);

        $unpaidTableOrders = collect();
        $isLastShift = $this->checkIsLastShift($restaurantId, $shift->id, $branchId);
        $autoPayEnabled = $this->isAutoPayEnabled($restaurantId, $branchId);

        if ($isLastShift && $autoPayEnabled) {
            $unpaidTableOrders = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('branch_id', $branchId)
                ->whereNotNull('table_id')
                ->where('payment_status', 'unpaid')
                ->whereIn('status', ['pending', 'confirmed', 'preparing'])
                ->get(['id', 'total_amount', 'discount_amount', 'is_split', 'is_override_split_penalty', 'is_red_flagged', 'order_number', 'created_at']);
        }

        $completedOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->get(['id', 'total_amount', 'discount_amount']);

        $allCompletedOrders = $completedOrders->concat($unpaidTableOrders);
        $orderIds = $allCompletedOrders->pluck('id');

        $grossRevenue = $allCompletedOrders->sum('total_amount');
        $discountTotal = $allCompletedOrders->sum('discount_amount');
        $netRevenue = $grossRevenue - $discountTotal;

        $payments = Payment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDt, $endDt])
            ->whereIn('order_id', $completedOrders->pluck('id')->all())
            ->get(['payment_method', 'amount']);

        $expectedCash = (float) $payments->where('payment_method', 'cash')->sum('amount');
        $expectedCash += (float) $unpaidTableOrders->sum('total_amount');

        $bankTransferAmount = (float) $payments->where('payment_method', 'bank_transfer')->sum('amount');
        $cardAmount = (float) $payments->where('payment_method', 'card')->sum('amount');
        $ewalletAmount = (float) $payments->where('payment_method', 'ewallet')->sum('amount');
        $mixedAmount = (float) $payments->where('payment_method', 'mixed')->sum('amount');
        $transferAmount = $bankTransferAmount + $cardAmount + $ewalletAmount + $mixedAmount;

        // Tính phạt đơn tách chưa đối soát (bao gồm đơn đã hoàn thành và đơn chưa thanh toán)
        $splitPenaltyTotal = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('is_split', true)
            ->where('is_override_split_penalty', false)
            ->where(function ($q) use ($startDt, $endDt) {
                $q->where(fn ($q2) => $q2->where('status', 'completed')->whereBetween('completed_at', [$startDt, $endDt]))
                    ->orWhere(fn ($q2) => $q2->where('payment_status', 'unpaid')->whereBetween('created_at', [$startDt, $endDt]));
            })
            ->sum('total_amount');

        // Cash register checking
        $register = CashRegister::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $shift->id)
            ->where('status', 'open')
            ->where(function ($q) use ($request) {
                $q->where('cashier_user_id', $request->user()->id)
                    ->orWhere('opened_by', $request->user()->id);
            })
            ->first();

        $openingBalance = 0.0;
        $otherCashIn = 0.0;
        $otherCashOut = 0.0;
        $hasRegister = false;

        if ($register) {
            $hasRegister = true;
            $openingBalance = (float) $register->opening_balance;
            $otherCashIn = (float) CashTransaction::where('cash_register_id', $register->id)
                ->where('type', 'in')
                ->where('source', 'other')
                ->sum('amount');
            $otherCashOut = (float) CashTransaction::where('cash_register_id', $register->id)
                ->where('type', 'out')
                ->sum('amount');
        }

        $expectedCashAfterPenalty = max(0.0, $openingBalance + $expectedCash - $splitPenaltyTotal + $otherCashIn - $otherCashOut);
        $netRevenueAfterPenalty = max(0.0, $netRevenue - $splitPenaltyTotal);

        $splitOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('is_split', true)
            ->where(function ($q) use ($startDt, $endDt) {
                $q->where(fn ($q2) => $q2->where('status', 'completed')->whereBetween('completed_at', [$startDt, $endDt]))
                    ->orWhere(fn ($q2) => $q2->where('payment_status', 'unpaid')->whereBetween('created_at', [$startDt, $endDt]));
            })
            ->get(['id', 'order_number', 'total_amount', 'is_override_split_penalty', 'is_red_flagged'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total_amount' => (float) $o->total_amount,
                'is_override_split_penalty' => (bool) $o->is_override_split_penalty,
                'is_red_flagged' => (bool) $o->is_red_flagged,
            ]);

        $pendingOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->whereBetween('created_at', [$startDt, $endDt])
            ->count();

        $unpaidInShiftCount = $unpaidTableOrders->filter(function ($o) use ($startDt, $endDt) {
            return $o->created_at >= $startDt && $o->created_at <= $endDt;
        })->count();
        $pendingOrders = max(0, $pendingOrders - $unpaidInShiftCount);

        $areaFilter = $request->input('area_id');
        $areaName = $this->areaSelectionName($restaurantId, $branchId, $areaFilter);
        $isAreaScoped = $this->isAreaScoped($areaFilter);

        $alreadyClosed = ShiftClosing::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $shift->id)
            ->where('area_name', $areaName)
            ->whereDate('closing_date', $closingDate)
            ->exists();

        $areasBreakdown = $this->getAreaBreakdownForShift(
            $restaurantId,
            $shift->id,
            $closingDate->toDateString(),
            $branchId,
            $closingAt,
        );
        if ($isAreaScoped) {
            $areasBreakdown = $this->filterBreakdownForArea($areasBreakdown, $areaFilter);
        }
        $summary = $this->summarizeBreakdown($areasBreakdown, $areaName);
        $expectedCashForSlip = $isAreaScoped
            ? $summary['cash_sales_amount']
            : $expectedCashAfterPenalty;
        $transferAmountForSlip = $isAreaScoped
            ? $summary['transfer_amount']
            : $transferAmount;

        $blindCount = $this->resolveBlindCountState(
            $restaurantId,
            $branchId,
            (int) $shift->id,
            $closingDate->toDateString(),
            $areaName,
        );

        $payload = [
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'start_time' => $startDt->format('H:i d/m/Y'),
            'end_time' => $endDt->format('H:i d/m/Y'),
            'period_start_at' => $startDt->toIso8601String(),
            'period_end_at' => $endDt->toIso8601String(),
            'is_overnight' => (bool) $shift->is_overnight,
            'area_name' => $areaName,
            'order_count' => $summary['order_count'],
            'total_order_count' => $summary['total_order_count'],
            'cash_order_count' => $summary['cash_order_count'],
            'transfer_order_count' => $summary['transfer_order_count'],
            'cancelled_order_count' => $summary['cancelled_order_count'],
            'cancelled_total_amount' => $summary['cancelled_total_amount'],
            'refunded_order_count' => $summary['refunded_order_count'],
            'refunded_total_amount' => $summary['refunded_total_amount'],
            'gross_revenue' => $summary['gross_revenue'],
            'discount_total' => $summary['discount_total'],
            'net_revenue' => $summary['net_revenue'],
            'cash_sales_amount' => $summary['cash_sales_amount'],
            'expected_cash' => $expectedCashForSlip,
            'bank_transfer' => $bankTransferAmount,
            'card' => $cardAmount,
            'ewallet' => $ewalletAmount,
            'mixed' => $mixedAmount,
            'transfer_amount' => $transferAmountForSlip,
            'pending_orders' => $pendingOrders,
            'already_closed' => $alreadyClosed,
            'areas_breakdown' => $areasBreakdown,
            'split_penalty_total' => (float) $splitPenaltyTotal,
            'split_orders' => $splitOrders,
            'opening_balance' => $openingBalance,
            'other_cash_in' => $otherCashIn,
            'other_cash_out' => $otherCashOut,
            'has_register' => $hasRegister,
            'closing_at' => $closingAt->toIso8601String(),
            'blind_count_required' => $blindCount['required'],
            'cash_count_id' => $blindCount['count']?->id,
            'counted_cash' => $blindCount['count'] ? (float) $blindCount['count']->total_counted : null,
            'variance_threshold' => CashControlSettings::varianceThreshold($restaurantId, $branchId),
            'evidence_threshold' => CashControlSettings::evidenceThreshold($restaurantId, $branchId),
        ];

        // Dem mu: giau moi con so cho phep suy ra tien mat ky vong, cho toi khi
        // thu ngan da nop phieu dem.
        if ($blindCount['required']) {
            $payload = $this->maskCashFigures($payload);
        }

        return response()->json($payload);
    }

    /**
     * Nhan phieu dem tien, sau do moi lo so ky vong.
     *
     * Tong luon duoc may chu tinh lai tu chi tiet menh gia - khong tin so tong
     * client gui len, vi sua tong de hon sua tung menh gia cho khop.
     */
    public function countCash(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager', 'cashier']), 403);

        $data = $request->validate([
            'shift_id' => ['required', 'integer', TenantRule::exists('work_shifts')],
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'area_id' => ['nullable'],
            'denominations' => ['required', 'array', 'min:1'],
            'denominations.*' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $restaurantId = $user->restaurant_id;
        $branchId = $this->resolveOperationalBranch($user);
        $areaName = $this->areaSelectionName($restaurantId, $branchId, $data['area_id'] ?? null);
        $closingDate = Carbon::parse($data['closing_date'])->toDateString();

        $invalid = array_diff(array_keys($data['denominations']), array_map('strval', self::DENOMINATIONS));
        if ($invalid !== []) {
            return response()->json([
                'message' => 'Menh gia khong hop le: '.implode(', ', $invalid),
            ], 422);
        }

        $total = 0.0;
        foreach ($data['denominations'] as $denomination => $quantity) {
            $total += (float) $denomination * (int) $quantity;
        }

        $calculated = $this->calculateShiftRevenue(
            $restaurantId,
            (int) $data['shift_id'],
            $closingDate,
            $branchId,
            now(),
            is_numeric($data['area_id'] ?? null) ? (int) $data['area_id'] : null,
        );
        $expectedCash = (float) $calculated['expected_cash'];

        $sequence = 1 + (int) CashCount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $data['shift_id'])
            ->whereDate('closing_date', $closingDate)
            ->where('area_name', $areaName)
            ->max('sequence');

        $count = CashCount::create([
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'shift_id' => $data['shift_id'],
            'closing_date' => $closingDate,
            'area_name' => $areaName,
            'counted_by' => $user->id,
            'sequence' => $sequence,
            'denominations' => $data['denominations'],
            'total_counted' => round($total, 2),
            'counted_at' => now(),
            // Lo ngay trong cung thao tac: tu giay nay phieu dem bi khoa.
            'expected_revealed_at' => now(),
            'expected_cash_at_reveal' => $expectedCash,
        ]);

        return response()->json([
            'cash_count_id' => $count->id,
            'sequence' => $count->sequence,
            'total_counted' => (float) $count->total_counted,
            'expected_cash' => $expectedCash,
            'difference' => round($total - $expectedCash, 2),
            'variance_threshold' => CashControlSettings::varianceThreshold($restaurantId, $branchId),
            'evidence_threshold' => CashControlSettings::evidenceThreshold($restaurantId, $branchId),
        ]);
    }

    /**
     * Lấy phiếu đếm gắn với phiếu chốt ca và kiểm tra tính nhất quán.
     *
     * Trả về null khi chế độ đếm mù đang tắt. Khi bật, thiếu phiếu đếm hoặc số
     * chốt lệch số đã đếm đều bị chặn — nếu không, thu ngân chỉ cần gõ lại cho
     * khớp số kỳ vọng sau khi hệ thống đã lộ.
     */
    private function resolveCashCountForClosing(
        int $restaurantId,
        ?int $branchId,
        int $shiftId,
        string $closingDate,
        ?string $areaName,
        ?int $cashCountId,
        float $actualCash,
    ): ?CashCount {
        if (! CashControlSettings::blindCountEnabled($restaurantId, $branchId)) {
            return null;
        }

        $query = CashCount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $shiftId)
            ->whereDate('closing_date', $closingDate)
            ->where('area_name', $areaName)
            ->whereNull('shift_closing_id');

        $count = $cashCountId
            ? (clone $query)->whereKey($cashCountId)->first()
            : (clone $query)->orderByDesc('sequence')->first();

        if (! $count) {
            throw ValidationException::withMessages([
                'cash_count_id' => 'Phải đếm tiền két trước khi chốt ca.',
            ]);
        }

        // So khớp tới đồng: phiếu chốt phải phản ánh đúng số đã đếm.
        if (abs((float) $count->total_counted - $actualCash) > 0.01) {
            throw ValidationException::withMessages([
                'actual_cash' => sprintf(
                    'Số chốt (%sđ) khác số đã đếm (%sđ). Hãy đếm lại nếu số đếm chưa đúng.',
                    number_format($actualCash),
                    number_format((float) $count->total_counted),
                ),
            ]);
        }

        return $count;
    }

    /**
     * Phieu dem con hieu luc cho ca/ngay/khu vuc nay, neu co.
     *
     * @return array{required: bool, count: ?CashCount}
     */
    private function resolveBlindCountState(
        int $restaurantId,
        ?int $branchId,
        int $shiftId,
        string $closingDate,
        ?string $areaName,
    ): array {
        if (! CashControlSettings::blindCountEnabled($restaurantId, $branchId)) {
            return ['required' => false, 'count' => null];
        }

        $count = CashCount::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $shiftId)
            ->whereDate('closing_date', $closingDate)
            ->where('area_name', $areaName)
            ->whereNull('shift_closing_id')
            ->orderByDesc('sequence')
            ->first();

        return ['required' => $count === null, 'count' => $count];
    }

    /**
     * Xoa moi con so cho phep suy ra tien mat ky vong.
     */
    private function maskCashFigures(array $payload): array
    {
        foreach (['expected_cash', 'cash_sales_amount', 'opening_balance', 'other_cash_in', 'other_cash_out'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = null;
            }
        }

        if (is_array($payload['areas_breakdown'] ?? null)) {
            $payload['areas_breakdown'] = array_map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }

                foreach (array_keys($row) as $key) {
                    if (str_contains((string) $key, 'cash')) {
                        $row[$key] = null;
                    }
                }

                return $row;
            }, $payload['areas_breakdown']);
        }

        return $payload;
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(['owner', 'manager', 'cashier']), 403);

        $data = $request->validate([
            'shift_id' => ['required', 'integer', TenantRule::exists('work_shifts')],
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'area_id' => ['nullable'],
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'cash_count_id' => ['nullable', 'integer'],
            'variance_explanation' => ['nullable', 'string', 'max:2000'],
            'actual_transfer_amount' => ['nullable', 'numeric', 'min:0'],
            'responsibility_amount' => ['nullable', 'numeric'],
            'responsibility_note' => ['nullable', 'string', 'max:1000'],
            'other_expense_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submit' => ['nullable', 'in:0,1'],
        ]);

        if (! $user->isOwner() && ! $user->isSuperAdmin()) {
            unset($data['responsibility_amount'], $data['other_expense_amount']);
        }

        $restaurantId = $user->restaurant_id;

        $branchId = $this->resolveOperationalBranch($user);

        $status = $request->boolean('submit') ? 'submitted' : 'draft';
        $notes = $data['notes'] ?? null;
        $closingAt = now();
        $areaFilter = $data['area_id'] ?? null;
        $areaName = $this->areaSelectionName($restaurantId, $branchId, $areaFilter);
        $isAreaScoped = $this->isAreaScoped($areaFilter);
        $areaId = $isAreaScoped ? (int) $areaFilter : null;
        $isLastShift = $this->checkIsLastShift($restaurantId, $data['shift_id'], $branchId);
        $autoPayEnabled = $this->isAutoPayEnabled($restaurantId, $branchId);

        $openRegistersForShift = CashRegister::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $data['shift_id'])
            ->where('status', 'open')
            ->when($isAreaScoped, fn ($query) => $query->where('area_id', $areaId))
            ->get();

        if (! $isAreaScoped && $openRegistersForShift->count() > 1) {
            return back()->withErrors([
                'area_id' => 'Chi nhánh có nhiều két theo khu vực. Vui lòng chọn khu vực để chốt đúng két.',
            ]);
        }

        try {
            DB::transaction(function () use ($restaurantId, $branchId, $data, $user, $isLastShift, $autoPayEnabled, $status, $closingAt, $areaFilter, $areaName, $isAreaScoped, $areaId, &$notes) {
                // Kiểm tra lại trong transaction với lock để tránh race condition
                $existsInLock = ShiftClosing::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $branchId)
                    ->where('shift_id', $data['shift_id'])
                    ->where('area_name', $areaName)
                    ->whereDate('closing_date', $data['closing_date'])
                    ->lockForUpdate()
                    ->exists();

                if ($existsInLock) {
                    throw new \Exception('Ca này đã được chốt cho ngày đã chọn.');
                }

                // Tự động thanh toán các đơn chưa thanh toán tại bàn nếu là ca cuối & bật chế độ auto-pay
                if ($isLastShift && $autoPayEnabled) {
                    $shift = WorkShift::withoutGlobalScopes()
                        ->where('restaurant_id', $restaurantId)
                        ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                        ->findOrFail($data['shift_id']);
                    $closingDate = Carbon::parse($data['closing_date']);
                    [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate, $closingAt);

                    $unpaidOrders = Order::withoutGlobalScopes()
                        ->where('restaurant_id', $restaurantId)
                        ->where('branch_id', $branchId)
                        ->whereNotNull('table_id')
                        ->where('payment_status', 'unpaid')
                        ->whereIn('status', ['pending', 'confirmed', 'preparing'])
                        ->lockForUpdate()
                        ->get();

                    // Lưu snapshot trước khi auto-pay
                    $this->saveBeforeAutoPaySnapshot($restaurantId, $branchId, $data['shift_id'], $data['closing_date'], $unpaidOrders);

                    foreach ($unpaidOrders as $order) {
                        $this->processAutoPay($order, $user, $endDt);
                    }
                }

                $calculated = $this->calculateShiftRevenue(
                    $restaurantId,
                    $data['shift_id'],
                    $data['closing_date'],
                    $branchId,
                    $closingAt,
                    $areaId,
                );
                $areasBreakdown = $this->getAreaBreakdownForShift(
                    $restaurantId,
                    $data['shift_id'],
                    $data['closing_date'],
                    $branchId,
                    $closingAt,
                );
                if ($isAreaScoped) {
                    $areasBreakdown = $this->filterBreakdownForArea($areasBreakdown, $areaFilter);
                }
                $summary = $this->summarizeBreakdown($areasBreakdown, $areaName);
                $expectedCashForSlip = $calculated['expected_cash'];

                if ($status === 'submitted' && $calculated['register_id']) {
                    $registerForClose = CashRegister::where('restaurant_id', $restaurantId)
                        ->whereKey($calculated['register_id'])
                        ->first();
                    if ($registerForClose?->requires_opening_reconciliation) {
                        throw ValidationException::withMessages([
                            'area_id' => 'Két được tự mở vì nhân viên quên mở ca. Quản lý phải đối soát số dư đầu ca trước khi chốt.',
                        ]);
                    }
                }
                $transferAmountForSlip = $isAreaScoped
                    ? $summary['transfer_amount']
                    : $calculated['transfer_amount'];
                $actualTransfer = (float) ($data['actual_transfer_amount'] ?? 0);
                $cashDifference = (float) $data['actual_cash'] - $expectedCashForSlip;
                $transferDifference = $actualTransfer - $transferAmountForSlip;
                $totalDifference = $cashDifference + $transferDifference;
                $isFinancialAuthority = $user->isOwner() || $user->isSuperAdmin();
                $responsibilityAmount = $isFinancialAuthority
                    && array_key_exists('responsibility_amount', $data)
                    && ! is_null($data['responsibility_amount'])
                    ? (float) $data['responsibility_amount']
                    : ($isFinancialAuthority ? $totalDifference : min(0.0, $totalDifference));

                if (! $isAreaScoped && $calculated['split_penalty_total'] > 0) {
                    $notes = trim(($notes ?? '')."\n[Khấu trừ đơn tách] Phạt đơn tách chưa đối soát: -".number_format($calculated['split_penalty_total']).'đ');
                }

                // Đếm mù: phải có phiếu đếm, và số chốt phải đúng bằng số đã
                // đếm. Nếu không, thu ngân vẫn có thể gõ lại cho khớp kỳ vọng
                // sau khi hệ thống lộ số.
                $cashCount = $this->resolveCashCountForClosing(
                    $restaurantId,
                    $branchId,
                    (int) $data['shift_id'],
                    $data['closing_date'],
                    $areaName,
                    $data['cash_count_id'] ?? null,
                    (float) $data['actual_cash'],
                );

                // Chênh lệch vượt ngưỡng thì bắt buộc giải trình ngay lúc chốt.
                $threshold = CashControlSettings::varianceThreshold($restaurantId, $branchId);
                $explanation = trim((string) ($data['variance_explanation'] ?? ''));

                if (abs($cashDifference) > $threshold && $explanation === '') {
                    throw ValidationException::withMessages([
                        'variance_explanation' => sprintf(
                            'Chênh lệch %sđ vượt ngưỡng %sđ — bắt buộc nhập giải trình.',
                            number_format(abs($cashDifference)),
                            number_format($threshold),
                        ),
                    ]);
                }

                // Một ca + khu vực chỉ có đúng một phiếu tổng.
                $closing = ShiftClosing::create([
                    'restaurant_id' => $restaurantId,
                    'branch_id' => $branchId,
                    'shift_id' => $data['shift_id'],
                    'closing_date' => $data['closing_date'],
                    'period_start_at' => $calculated['period_start_at'],
                    'area_id' => is_numeric($areaFilter) ? (int) $areaFilter : null,
                    'area_name' => $areaName,
                    'order_count' => $summary['order_count'],
                    'total_order_count' => $summary['total_order_count'],
                    'cash_order_count' => $summary['cash_order_count'],
                    'transfer_order_count' => $summary['transfer_order_count'],
                    'cancelled_order_count' => $summary['cancelled_order_count'],
                    'cancelled_total_amount' => $summary['cancelled_total_amount'],
                    'refunded_order_count' => $summary['refunded_order_count'],
                    'refunded_total_amount' => $summary['refunded_total_amount'],
                    'cashier_user_id' => $user->id,
                    'expected_cash' => $expectedCashForSlip,
                    'cash_sales_amount' => $summary['cash_sales_amount'],
                    'actual_cash' => $data['actual_cash'],
                    'cash_difference' => $cashDifference,
                    'transfer_amount' => $transferAmountForSlip,
                    'actual_transfer_amount' => $actualTransfer,
                    'transfer_difference' => $transferDifference,
                    'gross_revenue_amount' => $summary['gross_revenue'],
                    'discount_amount' => $summary['discount_total'],
                    'net_revenue_amount' => $summary['net_revenue'],
                    'total_difference' => $totalDifference,
                    'responsibility_amount' => $responsibilityAmount,
                    'responsibility_note' => $data['responsibility_note'] ?? null,
                    'other_expense_amount' => $data['other_expense_amount'] ?? 0,
                    'notes' => $notes,
                    'status' => $status,
                    'closed_at' => $closingAt,
                    'cash_register_id' => $calculated['register_id'],
                    'cash_count_id' => $cashCount?->id,
                    'variance_explanation' => $explanation !== '' ? $explanation : null,
                    'variance_explained_at' => $explanation !== '' ? now() : null,
                ]);

                // Gắn phiếu đếm vào phiếu chốt để nó không bị dùng lại cho ca sau.
                $cashCount?->update(['shift_closing_id' => $closing->id]);

                if ($calculated['register_id'] && $status === 'submitted') {
                    $register = CashRegister::where('restaurant_id', $restaurantId)
                        ->where('branch_id', $branchId)
                        ->find($calculated['register_id']);
                    if ($register) {
                        $register->update([
                            'status' => 'closed',
                            'open_scope_key' => null,
                            'closed_by' => $user->id,
                            'closed_at' => now(),
                            'closing_balance' => $data['actual_cash'],
                            'expected_closing_balance' => $expectedCashForSlip,
                            'difference' => $cashDifference,
                        ]);
                    }
                }
            });
        } catch (ValidationException $e) {
            // Lỗi kiểm tra dữ liệu đã có sẵn khóa trường của nó (cash_count_id,
            // actual_cash, variance_explanation...). Nuốt vào 'shift_id' sẽ làm
            // mất thông tin và giao diện không biết ô nào đang sai.
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['shift_id' => $e->getMessage()]);
        }

        $message = $status === 'submitted'
            ? 'Đã nộp phiếu chốt ca, chờ manager xét duyệt.'
            : 'Đã lưu bản nháp chốt ca.';

        return redirect()->route('shift-closings.index')->with('success', $message);
    }

    public function confirm(Request $request, ShiftClosing $closing): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($closing->status !== 'submitted', 422);
        $this->authorizeClosingBranch($request->user(), $closing);

        // Người chốt ca không tự xác nhận phiếu của chính mình — nếu không thì
        // một Quản lý kiêm thu ngân sẽ vừa đếm vừa duyệt chênh lệch của mình.
        abort_if(
            (int) $closing->cashier_user_id === (int) $request->user()->id,
            403,
            'Bạn không thể tự xác nhận phiếu chốt ca do chính mình lập.',
        );

        // Chênh lệch vượt ngưỡng mà chưa có giải trình thì không xác nhận được.
        $threshold = CashControlSettings::varianceThreshold(
            (int) $closing->restaurant_id,
            $closing->branch_id,
        );

        if (abs((float) $closing->cash_difference) > $threshold && blank($closing->variance_explanation)) {
            return back()->withErrors([
                'error' => 'Phiếu có chênh lệch vượt ngưỡng nhưng chưa có giải trình của thu ngân.',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $user = $request->user();

        DB::transaction(function () use ($closing, $restaurantId, $user) {
            // Khóa bản ghi closing trong transaction để tránh confirm đồng thời
            $closing = ShiftClosing::where('restaurant_id', $restaurantId)
                ->where('branch_id', $closing->branch_id)
                ->where('id', $closing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($closing->status !== 'submitted') {
                return; // Đã được xử lý bởi request khác
            }

            $closing->update([
                'status' => 'confirmed',
                'confirmed_by' => $user->id,
                'variance_confirmed_by' => $closing->variance_explanation ? $user->id : null,
                'variance_confirmed_at' => $closing->variance_explanation ? now() : null,
            ]);

            // Quy trách nhiệm theo số đã nhập: âm là trừ lương, dương là cộng lương.
            $responsibilityAmount = is_null($closing->responsibility_amount)
                ? (float) $closing->cash_difference
                : (float) $closing->responsibility_amount;

            if ($responsibilityAmount !== 0.0 && $closing->cashier_user_id) {
                $employee = Employee::withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->where('branch_id', $closing->branch_id)
                    ->where('user_id', $closing->cashier_user_id)
                    ->first();

                if ($employee) {
                    // Idempotency guard: không tạo duplicate (kiểm tra trong cùng transaction)
                    $alreadyExists = SalaryAdjustment::withoutGlobalScopes()
                        ->where('reference_id', $closing->id)
                        ->where('reference_type', ShiftClosing::class)
                        ->exists();

                    if (! $alreadyExists) {
                        $dateStr = Carbon::parse($closing->closing_date)->toDateString();
                        $shiftName = $closing->shift?->name ?? 'ca';
                        $adjustmentType = $responsibilityAmount < 0 ? 'cash_shortage' : 'bonus';
                        $adjustmentAmount = abs($responsibilityAmount);

                        $salaryService = app(SalaryService::class);
                        $salary = $salaryService->getOrCreateDraft($restaurantId, $employee, $dateStr);
                        $salaryService->addAdjustment($salary, [
                            'employee_id' => $employee->id,
                            'type' => $adjustmentType,
                            'amount' => $adjustmentAmount,
                            'reason' => ($responsibilityAmount < 0 ? 'Trừ trách nhiệm' : 'Cộng trách nhiệm')." {$shiftName} ngày ".Carbon::parse($closing->closing_date)->format('d/m/Y').': '.number_format($adjustmentAmount).'đ',
                            'reference_id' => $closing->id,
                            'reference_type' => ShiftClosing::class,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Đã xác nhận chốt ca thành công.');
    }

    public function dispute(Request $request, ShiftClosing $closing): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_unless(in_array($closing->status, ['submitted', 'confirmed']), 422);
        $this->authorizeClosingBranch($request->user(), $closing);

        $request->validate(['dispute_notes' => ['required', 'string', 'max:1000']]);

        $closing->update([
            'status' => 'disputed',
            'notes' => trim(($closing->notes ?? '')."\n[Tranh chấp] ".$request->input('dispute_notes')),
        ]);

        return back()->with('success', 'Đã đánh dấu tranh chấp.');
    }

    // ── Thùng rác ─────────────────────────────────────────────────────────────

    /**
     * Chuyển một phiếu chốt ca nháp vào thùng rác.
     * Chỉ owner/manager được phép. Chỉ áp dụng cho status = 'draft'.
     * Phiếu sẽ bị xóa vĩnh viễn sau 7 ngày bởi scheduled command.
     */
    public function trash(Request $request, ShiftClosing $closing): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_unless($closing->status === 'draft', 422, 'Chỉ được chuyển phiếu nháp vào thùng rác.');
        $this->authorizeClosingBranch($request->user(), $closing);

        $closing->update([
            'trashed_at' => now(),
            'trashed_by' => $request->user()->id,
        ]);

        AuditLog::record($closing, 'trashed', [], ['trashed_at' => now()->toDateTimeString()]);

        return back()->with('success', 'Đã chuyển phiếu nháp vào thùng rác. Sẽ tự xóa sau 7 ngày.');
    }

    /**
     * Khôi phục phiếu nháp từ thùng rác về danh sách chính.
     */
    public function restore(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);

        $closing = ShiftClosing::withTrashed()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->findOrFail($id);

        abort_unless($closing->isTrashed(), 422, 'Phiếu này không ở trong thùng rác.');
        $this->authorizeClosingBranch($request->user(), $closing);

        $closing->update([
            'trashed_at' => null,
            'trashed_by' => null,
        ]);

        AuditLog::record($closing, 'trash_restored', ['trashed_at' => $closing->getOriginal('trashed_at')], []);

        return back()->with('success', 'Đã khôi phục phiếu chốt ca từ thùng rác.');
    }

    /**
     * Danh sách thùng rác – trả về cùng view Index với flag viewingTrash = true.
     */
    public function trashIndex(Request $request): \Inertia\Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']),
            403,
        );

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        $monthFilter = $request->input('month', today()->format('Y-m'));
        [$year, $month] = explode('-', $monthFilter);

        $trashed = ShiftClosing::trashed()
            ->where('restaurant_id', $restaurantId)
            ->with(['shift', 'cashier'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereYear('closing_date', $year)
            ->whereMonth('closing_date', $month)
            ->latest('trashed_at')
            ->get()
            ->map(fn (ShiftClosing $c) => [
                'id'             => $c->id,
                'closing_date'   => $c->closing_date->format('d/m/Y'),
                'closing_date_raw' => $c->closing_date->toDateString(),
                'shift_name'     => $c->shift?->name ?? '—',
                'area_name'      => $c->area_name ?? 'Khu vực chung',
                'cashier_name'   => $c->cashier?->name ?? '—',
                'status'         => $c->status,
                'gross_revenue'  => (float) ($c->gross_revenue_amount ?? 0),
                'trashed_at'     => $c->trashed_at?->format('H:i d/m/Y'),
                'purge_at'       => $c->trashed_at?->addDays(7)->format('d/m/Y'),
            ]);

        return Inertia::render('shift-closings/Index', [
            'closings'      => collect()->values(),
            'trashedClosings' => $trashed->values(),
            'shifts'        => collect(),
            'areas'         => collect(),
            'kpi'           => ['total_closings' => 0, 'total_gross' => 0, 'total_cash' => 0, 'total_transfer' => 0, 'total_difference' => 0],
            'filters'       => ['status' => 'all', 'month' => $monthFilter],
            'viewingTrash'  => true,
            'canConfirm'    => false,
            'isManager'     => $user->hasAnyRole(['owner', 'manager']),
            'isOwner'       => $user->isOwner() || $user->isSuperAdmin(),
            'cashControl'   => null,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateShiftRevenue(int $restaurantId, int $shiftId, string $date, ?int $branchId = null, ?CarbonInterface $closingAt = null, ?int $areaId = null): array
    {
        $shift = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->findOrFail($shiftId);
        $closingDate = Carbon::parse($date);

        [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate, $closingAt);

        $orderIds = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($areaId !== null, fn ($q) => $q->whereHas('table', fn ($tableQuery) => $tableQuery->where('area_id', $areaId)))
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->pluck('id');

        $payments = Payment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDt, $endDt])
            ->whereIn('order_id', $orderIds->all())
            ->get(['payment_method', 'amount']);

        $splitPenaltyTotal = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('is_split', true)
            ->where('is_override_split_penalty', false)
            ->where(function ($q) use ($startDt, $endDt) {
                $q->where(fn ($q2) => $q2->where('status', 'completed')->whereBetween('completed_at', [$startDt, $endDt]))
                    ->orWhere(fn ($q2) => $q2->where('payment_status', 'unpaid')->whereBetween('created_at', [$startDt, $endDt]));
            })
            ->sum('total_amount');

        $expectedCash = (float) $payments->where('payment_method', 'cash')->sum('amount');

        $register = CashRegister::where('restaurant_id', $restaurantId)
            ->where('branch_id', $branchId)
            ->where('shift_id', $shiftId)
            ->when($areaId !== null, fn ($q) => $q->where('area_id', $areaId))
            ->where('status', 'open')
            ->when($areaId === null, fn ($q) => $q->where(function ($scope) {
                $scope->where('cashier_user_id', Auth::id())
                    ->orWhere('opened_by', Auth::id());
            }))
            ->first();

        $openingBalance = 0.0;
        $otherCashIn = 0.0;
        $otherCashOut = 0.0;
        $registerId = null;

        if ($register) {
            $registerId = $register->id;
            $openingBalance = (float) $register->opening_balance;
            $otherCashIn = (float) CashTransaction::where('cash_register_id', $register->id)
                ->where('type', 'in')
                ->where('source', 'other')
                ->sum('amount');
            $otherCashOut = (float) CashTransaction::where('cash_register_id', $register->id)
                ->where('type', 'out')
                ->sum('amount');
        }

        $expectedCashTotal = max(0.0, $openingBalance + $expectedCash - $splitPenaltyTotal + $otherCashIn - $otherCashOut);

        return [
            'period_start_at' => $startDt,
            'period_end_at' => $endDt,
            'expected_cash' => $expectedCashTotal,
            'cash_sales_amount' => (float) $expectedCash,
            'transfer_amount' => (float) $payments->whereIn('payment_method', ['bank_transfer', 'card', 'ewallet', 'mixed'])->sum('amount'),
            'split_penalty_total' => (float) $splitPenaltyTotal,
            'register_id' => $registerId,
        ];
    }

    private function shiftTimeRange(WorkShift $shift, Carbon $closingDate, ?CarbonInterface $closingAt = null): array
    {
        $startDt = Carbon::parse($closingDate->toDateString().' '.$shift->start_time);

        $scheduledEndDt = $shift->is_overnight
            ? Carbon::parse($closingDate->copy()->addDay()->toDateString().' '.$shift->end_time)
            : Carbon::parse($closingDate->toDateString().' '.$shift->end_time);

        // For a live close, the report ends exactly when the cashier presses
        // close. Historical dates keep their scheduled shift boundary.
        $isLiveClosing = $closingAt
            && ($closingDate->isToday() || ($shift->is_overnight && $closingDate->isYesterday()))
            && $closingAt->greaterThan($startDt);
        $endDt = $isLiveClosing ? $closingAt->copy() : $scheduledEndDt;

        return [$startDt, $endDt];
    }

    private function isAutoPayEnabled(int $restaurantId, ?int $branchId = null): bool
    {
        $setting = DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->where('key_name', 'auto_pay_on_last_shift_close')
            ->orderByRaw('branch_id IS NULL')
            ->value('value');

        if (is_null($setting)) {
            return false;
        }

        return filter_var(json_decode($setting) ?? $setting, FILTER_VALIDATE_BOOLEAN);
    }

    private function checkIsLastShift(int $restaurantId, int $shiftId, ?int $branchId = null): bool
    {
        $activeShifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->get();
        if ($activeShifts->isEmpty()) {
            return false;
        }

        $latestShift = $activeShifts->sortBy(function ($s) {
            try {
                $timeVal = Carbon::parse($s->end_time)->secondsSinceMidnight();
                if ($s->is_overnight) {
                    $timeVal += 86400;
                }

                return $timeVal;
            } catch (\Throwable $e) {
                return 0;
            }
        })->last();

        return $latestShift && $latestShift->id == $shiftId;
    }

    private function processAutoPay(Order $order, User $user, Carbon $completedAt): void
    {
        DB::transaction(function () use ($order, $user, $completedAt) {
            // Giữ thứ tự khóa giống luồng tạo/thanh toán POS: khóa bàn trước,
            // sau đó mới khóa order để tránh deadlock lúc chốt ca.
            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();
            }

            // Khoá row và đọc lại trạng thái mới nhất để tránh thanh toán trùng
            // khi cashier bấm thanh toán thủ công đồng thời với auto-pay lúc chốt ca.
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            if (! $order || $order->payment_status === 'paid') {
                return; // Đã được thanh toán bởi luồng khác, bỏ qua
            }

            $this->orderService->assertCanBePaid($order);

            // 1. Tạo Payment record
            Payment::create([
                'restaurant_id' => $order->restaurant_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'processed_by' => $user->id,
                'payment_method' => 'cash',
                'status' => 'paid',
                'amount' => $order->total_amount,
                'cash_received' => $order->total_amount,
                'change_amount' => 0,
                'paid_at' => $completedAt,
            ]);

            // 2. Trừ kho theo cùng BOM/FEFO/strict-stock policy như POS.
            $this->inventoryService->deductInventoryForOrder($order, $user);

            // 3. Cập nhật Order status thành completed & payment_status paid
            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'completed_at' => $completedAt,
                'cashier_user_id' => $user->id,
            ]);

            // Chỉ giải phóng bàn khi toàn bộ món đã phục vụ xong.
            if ($order->table_id) {
                $table = RestaurantTable::where('id', $order->table_id)
                    ->where('restaurant_id', $order->restaurant_id)
                    ->where('branch_id', $order->branch_id)
                    ->lockForUpdate()
                    ->first();

                if ($table && ! $table->orders()->activeForService()->exists()) {
                    $table->update(['status' => 'available']);
                }
            }

            AuditLog::log('order_paid', 'updated', $order, ['payment_status' => 'unpaid'], ['payment_status' => 'paid']);
        });
    }

    /**
     * Tạo file snapshot lưu trữ trạng thái các đơn hàng trước khi thực hiện auto-pay lúc chốt ca.
     */
    private function saveBeforeAutoPaySnapshot(int $restaurantId, int $branchId, int $shiftId, string $closingDate, Collection $orders): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $snapshot = [
            'restaurant_id' => $restaurantId,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'closing_date' => $closingDate,
            'created_at' => now()->toIso8601String(),
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount' => (float) $o->total_amount,
                'table_id' => $o->table_id,
            ])->toArray(),
        ];

        $dir = storage_path('app/snapshots');
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Loại bỏ ký tự lạ để tránh directory traversal
        $cleanDate = preg_replace('/[^0-9\-]/', '', $closingDate);
        $filename = "{$dir}/shift_closing_{$restaurantId}_branch_{$branchId}_{$shiftId}_{$cleanDate}_before_autopay.json";
        file_put_contents($filename, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('ShiftClosingController: Đã lưu snapshot trước auto-pay', ['file' => $filename]);
    }

    private function resolveOperationalBranch(User $user): int
    {
        $branchId = $this->tenantContext->activeBranchId()
            ?? $user->assignedBranchId()
            ?? RestaurantBranch::where('restaurant_id', $user->restaurant_id)->value('id');

        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Vui lòng chọn hoặc tạo chi nhánh cho cửa hàng trước khi thao tác chốt ca.',
            ]);
        }

        return (int) $branchId;
    }

    private function getAreaBreakdownForShift(int $restaurantId, int $shiftId, string $date, ?int $branchId = null, ?CarbonInterface $closingAt = null): array
    {
        $shift = WorkShift::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->findOrFail($shiftId);

        $closingDate = Carbon::parse($date);
        [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate, $closingAt);

        $areaGroups = [];

        $existingAreas = Area::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where(fn ($sq) => $sq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->get(['id', 'name']);

        foreach ($existingAreas as $areaObj) {
            $areaGroups['area_'.$areaObj->id] = [
                'area_id' => $areaObj->id,
                'area_name' => $areaObj->name,
                'total_order_count' => 0,
                'order_count' => 0,
                'cash_order_count' => 0,
                'expected_cash' => 0.0,
                'transfer_order_count' => 0,
                'transfer_amount' => 0.0,
                'cancelled_order_count' => 0,
                'cancelled_total_amount' => 0.0,
                'refunded_order_count' => 0,
                'refunded_total_amount' => 0.0,
                'gross_revenue' => 0.0,
                'discount_total' => 0.0,
                'net_revenue' => 0.0,
            ];
        }

        $takeawayKey = 'takeaway';
        $areaGroups[$takeawayKey] = [
            'area_id' => null,
            'area_name' => 'Mang về / Giao hàng',
            'total_order_count' => 0,
            'order_count' => 0,
            'cash_order_count' => 0,
            'expected_cash' => 0.0,
            'transfer_order_count' => 0,
            'transfer_amount' => 0.0,
            'cancelled_order_count' => 0,
            'cancelled_total_amount' => 0.0,
            'refunded_order_count' => 0,
            'refunded_total_amount' => 0.0,
            'gross_revenue' => 0.0,
            'discount_total' => 0.0,
            'net_revenue' => 0.0,
        ];

        $shiftOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where(function ($q) use ($startDt, $endDt) {
                $q->whereBetween('created_at', [$startDt, $endDt])
                    ->orWhereBetween('completed_at', [$startDt, $endDt])
                    ->orWhereBetween('cancelled_at', [$startDt, $endDt])
                    ->orWhereBetween('refunded_at', [$startDt, $endDt]);
            })
            ->with(['table.area', 'payments'])
            ->get();

        foreach ($shiftOrders as $ord) {
            $areaObj = $ord->table?->area;
            $groupKey = $areaObj ? 'area_'.$areaObj->id : $takeawayKey;

            if (! isset($areaGroups[$groupKey])) {
                $areaGroups[$groupKey] = [
                    'area_id' => $areaObj?->id,
                    'area_name' => $areaObj?->name ?? 'Mang về / Giao hàng',
                    'total_order_count' => 0,
                    'order_count' => 0,
                    'cash_order_count' => 0,
                    'expected_cash' => 0.0,
                    'transfer_order_count' => 0,
                    'transfer_amount' => 0.0,
                    'cancelled_order_count' => 0,
                    'cancelled_total_amount' => 0.0,
                    'refunded_order_count' => 0,
                    'refunded_total_amount' => 0.0,
                    'gross_revenue' => 0.0,
                    'discount_total' => 0.0,
                    'net_revenue' => 0.0,
                ];
            }

            if ($ord->created_at >= $startDt && $ord->created_at <= $endDt) {
                $areaGroups[$groupKey]['total_order_count'] += 1;
            }

            if ($ord->status === 'cancelled' && (($ord->cancelled_at && $ord->cancelled_at >= $startDt && $ord->cancelled_at <= $endDt) || ($ord->updated_at >= $startDt && $ord->updated_at <= $endDt))) {
                $areaGroups[$groupKey]['cancelled_order_count'] += 1;
                $areaGroups[$groupKey]['cancelled_total_amount'] += (float) $ord->total_amount;
            }

            if ($ord->payment_status === 'refunded' && (($ord->refunded_at && $ord->refunded_at >= $startDt && $ord->refunded_at <= $endDt) || ($ord->updated_at >= $startDt && $ord->updated_at <= $endDt))) {
                $areaGroups[$groupKey]['refunded_order_count'] += 1;
                $areaGroups[$groupKey]['refunded_total_amount'] += (float) $ord->total_amount;
            }

            if ($ord->status === 'completed') {
                $areaGroups[$groupKey]['order_count'] += 1;
                $areaGroups[$groupKey]['discount_total'] += (float) $ord->discount_amount;

                $hasCash = false;
                $hasTransfer = false;

                foreach ($ord->payments as $pm) {
                    if ($pm->status === 'paid') {
                        if ($pm->payment_method === 'cash') {
                            $areaGroups[$groupKey]['expected_cash'] += (float) $pm->amount;
                            $hasCash = true;
                        } else {
                            $areaGroups[$groupKey]['transfer_amount'] += (float) $pm->amount;
                            $hasTransfer = true;
                        }
                    }
                }

                if ($hasCash) {
                    $areaGroups[$groupKey]['cash_order_count'] += 1;
                }
                if ($hasTransfer) {
                    $areaGroups[$groupKey]['transfer_order_count'] += 1;
                }
            }
        }

        foreach ($areaGroups as $k => $g) {
            $gross = $g['expected_cash'] + $g['transfer_amount'] - $g['refunded_total_amount'];
            $areaGroups[$k]['gross_revenue'] = max(0.0, $gross);
            $areaGroups[$k]['net_revenue'] = max(0.0, $gross - $g['discount_total']);
        }

        $result = collect(array_values($areaGroups))
            ->filter(function ($item) {
                return $item['total_order_count'] > 0 || $item['order_count'] > 0 || $item['cancelled_order_count'] > 0 || $item['refunded_order_count'] > 0;
            })
            ->values()
            ->all();

        if (empty($result)) {
            $result = array_values($areaGroups);
        }

        return $result;
    }

    /**
     * Collapse the per-area calculation into the single immutable slip shown
     * to the cashier and used by payroll reconciliation.
     */
    private function summarizeBreakdown(array $breakdown, string $areaName = 'Toàn bộ khu vực'): array
    {
        $sum = static fn (string $key): float => (float) collect($breakdown)->sum(
            fn (array $item): float => (float) ($item[$key] ?? 0),
        );

        return [
            'area_name' => $areaName,
            'order_count' => (int) $sum('order_count'),
            'total_order_count' => (int) $sum('total_order_count'),
            'cash_order_count' => (int) $sum('cash_order_count'),
            'transfer_order_count' => (int) $sum('transfer_order_count'),
            'cancelled_order_count' => (int) $sum('cancelled_order_count'),
            'cancelled_total_amount' => $sum('cancelled_total_amount'),
            'refunded_order_count' => (int) $sum('refunded_order_count'),
            'refunded_total_amount' => $sum('refunded_total_amount'),
            'cash_sales_amount' => $sum('expected_cash'),
            'transfer_amount' => $sum('transfer_amount'),
            'gross_revenue' => $sum('gross_revenue'),
            'discount_total' => $sum('discount_total'),
            'net_revenue' => $sum('net_revenue'),
        ];
    }

    private function isAreaScoped(mixed $areaFilter): bool
    {
        return ! is_null($areaFilter) && $areaFilter !== '' && $areaFilter !== 'all';
    }

    private function filterBreakdownForArea(array $breakdown, mixed $areaFilter): array
    {
        if ($areaFilter === 'takeaway') {
            return array_values(array_filter($breakdown, fn (array $item): bool => is_null($item['area_id'] ?? null)));
        }

        return array_values(array_filter(
            $breakdown,
            fn (array $item): bool => (int) ($item['area_id'] ?? 0) === (int) $areaFilter,
        ));
    }

    private function areaSelectionName(int $restaurantId, ?int $branchId, mixed $areaFilter): string
    {
        if ($areaFilter === 'takeaway') {
            return 'Mang về / Giao hàng';
        }

        if ($this->isAreaScoped($areaFilter)) {
            return Area::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->when($branchId, fn ($q) => $q->where(fn ($scope) => $scope
                    ->where('branch_id', $branchId)
                    ->orWhereNull('branch_id')))
                ->whereKey((int) $areaFilter)
                ->value('name') ?? 'Khu vực đã chọn';
        }

        return 'Toàn bộ khu vực';
    }

    private function authorizeClosingBranch(User $user, ShiftClosing $closing): void
    {
        abort_if($closing->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($closing->branch_id), 403);

        if ($this->tenantContext->isBranchScoped()) {
            abort_unless($this->tenantContext->activeBranchId() === (int) $closing->branch_id, 403);
        }
    }
}
