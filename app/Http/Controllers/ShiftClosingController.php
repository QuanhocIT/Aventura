<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SalaryAdjustment;
use App\Models\ShiftClosing;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftClosingController extends Controller
{
    public function index(Request $request): Response
    {
        $user         = $request->user();
        $restaurantId = $user->restaurant_id;

        $statusFilter = $request->input('status', 'all');
        $monthFilter  = $request->input('month', today()->format('Y-m'));

        [$year, $month] = explode('-', $monthFilter);

        $query = ShiftClosing::where('restaurant_id', $restaurantId)
            ->with(['shift', 'cashier', 'confirmedBy'])
            ->whereYear('closing_date', $year)
            ->whereMonth('closing_date', $month)
            ->latest('closing_date');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $closings = $query->get()->map(fn (ShiftClosing $c) => [
            'id'                => $c->id,
            'closing_date'      => $c->closing_date->format('d/m/Y'),
            'closing_date_raw'  => $c->closing_date->toDateString(),
            'shift_name'        => $c->shift?->name ?? '—',
            'shift_code'        => $c->shift?->code ?? '',
            'shift_start'       => $c->shift?->start_time ?? '',
            'shift_end'         => $c->shift?->end_time ?? '',
            'cashier_name'      => $c->cashier?->name ?? '—',
            'status'            => $c->status,
            'expected_cash'     => (float) $c->expected_cash,
            'actual_cash'       => (float) $c->actual_cash,
            'cash_difference'   => (float) $c->cash_difference,
            'transfer_amount'   => (float) $c->transfer_amount,
            'gross_revenue'     => (float) ($c->expected_cash + $c->transfer_amount),
            'other_expense'     => (float) $c->other_expense_amount,
            'notes'             => $c->notes,
            'confirmed_by_name' => $c->confirmedBy?->name ?? null,
            'closed_at'         => $c->closed_at?->format('H:i d/m/Y'),
        ]);

        // KPI tổng tháng
        $kpi = [
            'total_closings'    => $closings->count(),
            'total_gross'       => $closings->sum('gross_revenue'),
            'total_cash'        => $closings->sum('actual_cash'),
            'total_transfer'    => $closings->sum('transfer_amount'),
            'total_difference'  => $closings->sum('cash_difference'),
        ];

        // Auto-seed ca mặc định nếu chưa có
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get(['id', 'name', 'code', 'start_time', 'end_time', 'is_overnight']);

        if ($shifts->isEmpty()) {
            foreach ([
                ['name' => 'Ca Sáng', 'code' => 'CA_SANG',  'start_time' => '06:00', 'end_time' => '14:00'],
                ['name' => 'Ca Chiều', 'code' => 'CA_CHIEU', 'start_time' => '14:00', 'end_time' => '22:00'],
                ['name' => 'Ca Tối',  'code' => 'CA_TOI',   'start_time' => '18:00', 'end_time' => '23:59'],
            ] as $ds) {
                WorkShift::withoutGlobalScopes()->firstOrCreate(
                    ['restaurant_id' => $restaurantId, 'code' => $ds['code']],
                    array_merge($ds, ['restaurant_id' => $restaurantId, 'status' => 'active', 'is_overnight' => false])
                );
            }
            $shifts = WorkShift::where('restaurant_id', $restaurantId)
                ->where('status', 'active')
                ->get(['id', 'name', 'code', 'start_time', 'end_time', 'is_overnight']);
        }

        return Inertia::render('shift-closings/Index', [
            'closings'   => $closings->values(),
            'shifts'     => $shifts,
            'kpi'        => $kpi,
            'filters'    => ['status' => $statusFilter, 'month' => $monthFilter],
            'canConfirm' => $user->hasAnyRole(['owner', 'manager']),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'shift_id'     => ['required', 'integer'],
            'closing_date' => ['required', 'date'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        $shift = WorkShift::where('restaurant_id', $restaurantId)
            ->findOrFail($request->integer('shift_id'));

        $closingDate = Carbon::parse($request->input('closing_date'));

        [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate);

        $completedOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->get(['id', 'total_amount', 'discount_amount']);

        $orderIds = $completedOrders->pluck('id');

        $grossRevenue  = $completedOrders->sum('total_amount');
        $discountTotal = $completedOrders->sum('discount_amount');
        $netRevenue    = $grossRevenue - $discountTotal;

        $payments = Payment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDt, $endDt])
            ->whereIn('order_id', $orderIds->all())
            ->get(['payment_method', 'amount']);

        $expectedCash      = (float) $payments->where('payment_method', 'cash')->sum('amount');
        $bankTransferAmount = (float) $payments->where('payment_method', 'bank_transfer')->sum('amount');
        $cardAmount         = (float) $payments->where('payment_method', 'card')->sum('amount');
        $ewalletAmount      = (float) $payments->where('payment_method', 'ewallet')->sum('amount');
        $mixedAmount        = (float) $payments->where('payment_method', 'mixed')->sum('amount');
        $transferAmount     = $bankTransferAmount + $cardAmount + $ewalletAmount + $mixedAmount;

        // Tính phạt đơn tách chưa đối soát
        $splitPenaltyTotal = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_split', true)
            ->where('is_override_split_penalty', false)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->sum('total_amount');

        $expectedCashAfterPenalty = max(0.0, $expectedCash - $splitPenaltyTotal);
        $netRevenueAfterPenalty   = max(0.0, $netRevenue - $splitPenaltyTotal);

        $splitOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_split', true)
            ->whereBetween('completed_at', [$startDt, $endDt])
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
            ->whereIn('status', ['pending', 'confirmed', 'preparing'])
            ->whereBetween('created_at', [$startDt, $endDt])
            ->count();

        $alreadyClosed = ShiftClosing::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('shift_id', $shift->id)
            ->whereDate('closing_date', $closingDate)
            ->exists();

        return response()->json([
            'shift_name'         => $shift->name,
            'shift_code'         => $shift->code,
            'start_time'         => $startDt->format('H:i d/m/Y'),
            'end_time'           => $endDt->format('H:i d/m/Y'),
            'is_overnight'       => (bool) $shift->is_overnight,
            'order_count'        => $completedOrders->count(),
            'gross_revenue'      => (float) $grossRevenue,
            'discount_total'     => (float) $discountTotal,
            'net_revenue'        => (float) $netRevenueAfterPenalty,
            'expected_cash'      => $expectedCashAfterPenalty,
            'bank_transfer'      => $bankTransferAmount,
            'card'               => $cardAmount,
            'ewallet'            => $ewalletAmount,
            'mixed'              => $mixedAmount,
            'transfer_amount'    => $transferAmount,
            'pending_orders'     => $pendingOrders,
            'already_closed'     => $alreadyClosed,
            'split_penalty_total'=> (float) $splitPenaltyTotal,
            'split_orders'       => $splitOrders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasAnyRole(['owner', 'manager', 'cashier']), 403);

        $data = $request->validate([
            'shift_id'             => ['required', 'integer', 'exists:work_shifts,id'],
            'closing_date'         => ['required', 'date', 'before_or_equal:today'],
            'actual_cash'          => ['required', 'numeric', 'min:0'],
            'other_expense_amount' => ['nullable', 'numeric', 'min:0'],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'submit'               => ['nullable', 'in:0,1'],
        ]);

        $restaurantId = $user->restaurant_id;

        $exists = ShiftClosing::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('shift_id', $data['shift_id'])
            ->whereDate('closing_date', $data['closing_date'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['shift_id' => 'Ca này đã được chốt cho ngày đã chọn.']);
        }

        $calculated = $this->calculateShiftRevenue($restaurantId, $data['shift_id'], $data['closing_date']);

        $cashDifference = (float) $data['actual_cash'] - $calculated['expected_cash'];
        $status = $request->boolean('submit') ? 'submitted' : 'draft';

        $notes = $data['notes'];
        if ($calculated['split_penalty_total'] > 0) {
            $notes = trim(($notes ?? '') . "\n[Khấu trừ đơn tách] Phạt đơn tách chưa đối soát: -" . number_format($calculated['split_penalty_total']) . "đ");
        }

        ShiftClosing::create([
            'restaurant_id'        => $restaurantId,
            'shift_id'             => $data['shift_id'],
            'closing_date'         => $data['closing_date'],
            'cashier_user_id'      => $user->id,
            'expected_cash'        => $calculated['expected_cash'],
            'actual_cash'          => $data['actual_cash'],
            'cash_difference'      => $cashDifference,
            'transfer_amount'      => $calculated['transfer_amount'],
            'other_expense_amount' => $data['other_expense_amount'] ?? 0,
            'notes'                => $notes,
            'status'               => $status,
            'closed_at'            => now(),
        ]);

        $message = $status === 'submitted'
            ? 'Đã nộp phiếu chốt ca, chờ manager xét duyệt.'
            : 'Đã lưu bản nháp chốt ca.';

        return redirect()->route('shift-closings.index')->with('success', $message);
    }

    public function confirm(Request $request, ShiftClosing $closing): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($closing->status !== 'submitted', 422);

        $restaurantId = $request->user()->restaurant_id;

        $closing->update([
            'status'       => 'confirmed',
            'confirmed_by' => $request->user()->id,
        ]);

        // Auto-tạo khấu trừ lương nếu cashier thiếu quỹ
        if ((float) $closing->cash_difference < 0 && $closing->cashier_user_id) {
            $employee = Employee::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('user_id', $closing->cashier_user_id)
                ->first();

            if ($employee) {
                // Idempotency guard: không tạo duplicate
                $alreadyExists = SalaryAdjustment::withoutGlobalScopes()
                    ->where('reference_id', $closing->id)
                    ->where('reference_type', ShiftClosing::class)
                    ->exists();

                if (! $alreadyExists) {
                    $dateStr      = Carbon::parse($closing->closing_date)->toDateString();
                    $shiftName    = $closing->shift?->name ?? 'ca';
                    $shortageAmt  = abs((float) $closing->cash_difference);

                    $salaryService = app(SalaryService::class);
                    $salary = $salaryService->getOrCreateDraft($restaurantId, $employee, $dateStr);
                    $salaryService->addAdjustment($salary, [
                        'employee_id'    => $employee->id,
                        'type'           => 'cash_shortage',
                        'amount'         => $shortageAmt,
                        'reason'         => "Thiếu quỹ {$shiftName} ngày " . Carbon::parse($closing->closing_date)->format('d/m/Y') . ': ' . number_format($shortageAmt) . 'đ',
                        'reference_id'   => $closing->id,
                        'reference_type' => ShiftClosing::class,
                    ]);
                }
            }
        }

        return back()->with('success', 'Đã xác nhận chốt ca thành công.');
    }

    public function dispute(Request $request, ShiftClosing $closing): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_unless(in_array($closing->status, ['submitted', 'confirmed']), 422);

        $request->validate(['dispute_notes' => ['required', 'string', 'max:1000']]);

        $closing->update([
            'status' => 'disputed',
            'notes'  => trim(($closing->notes ?? '') . "\n[Tranh chấp] " . $request->input('dispute_notes')),
        ]);

        return back()->with('success', 'Đã đánh dấu tranh chấp.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function calculateShiftRevenue(int $restaurantId, int $shiftId, string $date): array
    {
        $shift = WorkShift::withoutGlobalScopes()->findOrFail($shiftId);
        $closingDate = Carbon::parse($date);

        [$startDt, $endDt] = $this->shiftTimeRange($shift, $closingDate);

        $orderIds = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->pluck('id');

        $payments = Payment::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDt, $endDt])
            ->whereIn('order_id', $orderIds->all())
            ->get(['payment_method', 'amount']);

        $splitPenaltyTotal = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_split', true)
            ->where('is_override_split_penalty', false)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDt, $endDt])
            ->sum('total_amount');

        $expectedCash = (float) $payments->where('payment_method', 'cash')->sum('amount');
        $expectedCash = max(0.0, $expectedCash - $splitPenaltyTotal);

        return [
            'expected_cash'   => $expectedCash,
            'transfer_amount' => (float) $payments->whereIn('payment_method', ['bank_transfer', 'card', 'ewallet', 'mixed'])->sum('amount'),
            'split_penalty_total' => (float) $splitPenaltyTotal,
        ];
    }

    private function shiftTimeRange(WorkShift $shift, Carbon $closingDate): array
    {
        $startDt = Carbon::parse($closingDate->toDateString() . ' ' . $shift->start_time);

        $endDt = $shift->is_overnight
            ? Carbon::parse($closingDate->copy()->addDay()->toDateString() . ' ' . $shift->end_time)
            : Carbon::parse($closingDate->toDateString() . ' ' . $shift->end_time);

        return [$startDt, $endDt];
    }
}
