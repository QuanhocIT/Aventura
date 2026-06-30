<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class CashFlowController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Dòng tiền',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        // Restrict non-owners/managers to their branch if employee profile exists
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->branch_id && !$user->hasRole('owner')) {
            $branchId = $employee->branch_id;
        }

        // Active registers for this restaurant & branch
        $activeRegister = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'open')
            ->with(['openedBy', 'shift'])
            ->first();

        // Recent cash registers
        $registers = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with(['openedBy', 'closedBy', 'shift'])
            ->latest('id')
            ->take(20)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'closing_date' => $r->closing_date->format('d/m/Y'),
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
        if ($activeRegister) {
            $activeTransactions = CashTransaction::where('cash_register_id', $activeRegister->id)
                ->with('createdBy')
                ->latest('id')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'source' => $t->source,
                    'notes' => $t->notes,
                    'created_by_name' => $t->createdBy?->name ?? '—',
                    'occurred_at' => $t->occurred_at->format('H:i d/m/Y'),
                ]);
        }

        // Cash flow charts: last 30 days daily cash movements
        $thirtyDaysAgo = now()->subDays(29)->startOfDay();
        $dailyCashFlow = CashTransaction::where('restaurant_id', $restaurantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->selectRaw("DATE(occurred_at) as date, type, SUM(amount) as total")
            ->groupBy('date', 'type')
            ->get();

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            $label = $date->format('d/m');
            $in = (float) $dailyCashFlow->where('date', $dateStr)->where('type', 'in')->sum('total');
            $out = (float) $dailyCashFlow->where('date', $dateStr)->where('type', 'out')->sum('total');
            $chartData[] = [
                'date' => $label,
                'in' => $in,
                'out' => $out,
                'net' => $in - $out
            ];
        }

        // Active work shifts for opening register selection
        $shifts = WorkShift::where('restaurant_id', $restaurantId)
            ->where('status', 'active')
            ->get(['id', 'name', 'code']);

        // Cash Flow Forecast calculation
        $forecast = $this->calculateCashForecast($restaurantId, $branchId, $activeRegister);

        return Inertia::render('cash-flow/Index', [
            'activeRegister' => $activeRegister ? [
                'id' => $activeRegister->id,
                'opening_balance' => (float) $activeRegister->opening_balance,
                'expense_budget' => (float) $activeRegister->expense_budget,
                'opened_at' => $activeRegister->opened_at->format('H:i d/m/Y'),
                'opened_by_name' => $activeRegister->openedBy?->name ?? '—',
                'shift_name' => $activeRegister->shift?->name ?? '—',
                'closing_date' => $activeRegister->closing_date->toDateString(),
                'expected_cash' => $this->calculateLiveExpectedCash($activeRegister->id),
            ] : null,
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
        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        // Auto assign branch from employee profile
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->branch_id) {
            $branchId = $employee->branch_id;
        }

        $data = $request->validate([
            'shift_id'        => ['required', 'integer', 'exists:work_shifts,id'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'expense_budget'  => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
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
            'restaurant_id'   => $restaurantId,
            'branch_id'       => $branchId,
            'shift_id'        => $data['shift_id'],
            'closing_date'    => $closingDate,
            'opened_by'       => $user->id,
            'cashier_user_id' => $user->id,
            'opening_balance' => $data['opening_balance'],
            'expense_budget'  => $data['expense_budget'] ?? 0,
            'status'          => 'open',
            'opened_at'       => now(),
            'notes'           => $data['notes'],
        ]);

        return redirect()->route('cash-flow.index')->with('success', 'Đã mở két đầu ca thành công.');
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $branchId = $user->branch_id;

        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        if ($employee && $employee->branch_id) {
            $branchId = $employee->branch_id;
        }

        $data = $request->validate([
            'type'   => ['required', 'string', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:1'],
            'notes'  => ['required', 'string', 'max:500'],
            'source' => ['required', 'string', 'in:expense,other'],
        ]);

        $activeRegister = CashRegister::where('restaurant_id', $restaurantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'open')
            ->first();

        if (!$activeRegister) {
            return back()->withErrors(['amount' => 'Chưa có két tiền nào được mở. Vui lòng mở két tiền mặt trước khi tạo giao dịch.']);
        }

        // For out/expense transactions, we can perform budget alert checking
        if ($data['type'] === 'out' && $activeRegister->expense_budget > 0) {
            $existingOut = (float) CashTransaction::where('cash_register_id', $activeRegister->id)
                ->where('type', 'out')
                ->sum('amount');
            if ($existingOut + $data['amount'] > $activeRegister->expense_budget) {
                // We let them save, but we will show alerts elsewhere. We can append a warning note
                $data['notes'] .= ' [Vượt ngân sách chi tiêu ca]';
            }
        }

        CashTransaction::create([
            'restaurant_id'    => $restaurantId,
            'branch_id'        => $branchId,
            'cash_register_id' => $activeRegister->id,
            'type'             => $data['type'],
            'amount'           => $data['amount'],
            'source'           => $data['source'],
            'notes'            => $data['notes'],
            'created_by'       => $user->id,
            'occurred_at'      => now(),
        ]);

        $msg = $data['type'] === 'out' ? 'Ghi nhận khoản chi tiền mặt thành công.' : 'Ghi nhận khoản thu tiền mặt thành công.';
        return redirect()->route('cash-flow.index')->with('success', $msg);
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

    private function calculateCashForecast(int $restaurantId, ?int $branchId, ?CashRegister $activeRegister): array
    {
        $thirtyDaysAgo = now()->subDays(29)->startOfDay();

        // Calculate average daily cash in and out of the last 30 days
        $totals = CashTransaction::where('restaurant_id', $restaurantId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            ->selectRaw("type, SUM(amount) as total")
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $totalIn = $totals->get('in')?->total ?? 0;
        $totalOut = $totals->get('out')?->total ?? 0;

        $avgDailyIn = (float) ($totalIn / 30);
        $avgDailyOut = (float) ($totalOut / 30);

        // Get current active cash drawer cash or estimate
        $currentCash = 0;
        if ($activeRegister) {
            $currentCash = $this->calculateLiveExpectedCash($activeRegister->id);
        } else {
            // Check last closed register balance
            $lastClosed = CashRegister::where('restaurant_id', $restaurantId)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
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
            $message = 'Cảnh báo: Dòng tiền mặt có thể thiếu hụt trong 7 ngày tới. Dự kiến thâm hụt khoảng ' . number_format(abs($projectedBalance)) . 'đ. Vui lòng tăng dự trữ tiền mặt hoặc giảm bớt các khoản chi ngoài hệ thống.';
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
}
