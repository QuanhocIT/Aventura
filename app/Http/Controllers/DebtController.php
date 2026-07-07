<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\PurchaseOrder;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    /**
     * Display the debts dashboard & list of accounts payable/receivable.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $user->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Công nợ',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;

        // Statistics
        $totalReceivable = (float) AccountReceivable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount - received_amount) as total')
            ->value('total') ?? 0.0;

        $totalPayable = (float) AccountPayable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0.0;

        $today = now()->startOfDay();

        $overdueReceivable = (float) AccountReceivable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->selectRaw('SUM(amount - received_amount) as total')
            ->value('total') ?? 0.0;

        $overduePayable = (float) AccountPayable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0.0;

        // Receivables Aging Analysis
        $receivables = AccountReceivable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->get();

        $receivablesAging = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];
        foreach ($receivables as $ar) {
            $remaining = (float)$ar->amount - (float)$ar->received_amount;
            if ($remaining <= 0) continue;
            
            $dueDate = \Carbon\Carbon::parse($ar->due_date)->startOfDay();
            if ($dueDate->gte($today)) {
                $receivablesAging['current'] += $remaining;
            } else {
                $daysOverdue = $today->diffInDays($dueDate);
                if ($daysOverdue <= 30) {
                    $receivablesAging['1_30'] += $remaining;
                } elseif ($daysOverdue <= 60) {
                    $receivablesAging['31_60'] += $remaining;
                } elseif ($daysOverdue <= 90) {
                    $receivablesAging['61_90'] += $remaining;
                } else {
                    $receivablesAging['over_90'] += $remaining;
                }
            }
        }

        // Payables Aging Analysis
        $payables = AccountPayable::where('restaurant_id', $restaurantId)
            ->where('status', '!=', 'paid')
            ->get();

        $payablesAging = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];
        foreach ($payables as $ap) {
            $remaining = (float)$ap->amount - (float)$ap->paid_amount;
            if ($remaining <= 0) continue;
            
            $dueDate = \Carbon\Carbon::parse($ap->due_date)->startOfDay();
            if ($dueDate->gte($today)) {
                $payablesAging['current'] += $remaining;
            } else {
                $daysOverdue = $today->diffInDays($dueDate);
                if ($daysOverdue <= 30) {
                    $payablesAging['1_30'] += $remaining;
                } elseif ($daysOverdue <= 60) {
                    $payablesAging['31_60'] += $remaining;
                } elseif ($daysOverdue <= 90) {
                    $payablesAging['61_90'] += $remaining;
                } else {
                    $payablesAging['over_90'] += $remaining;
                }
            }
        }

        // Pagination and Filters for Payables List
        $payableStatus = $request->input('payable_status');
        $payablesQuery = AccountPayable::where('restaurant_id', $restaurantId)
            ->with(['supplier', 'purchaseOrder']);
        if ($payableStatus) {
            $payablesQuery->where('status', $payableStatus);
        }
        $payablesList = $payablesQuery->latest()->paginate(10, ['*'], 'payables_page')->withQueryString();

        // Pagination and Filters for Receivables List
        $receivableStatus = $request->input('receivable_status');
        $receivablesQuery = AccountReceivable::where('restaurant_id', $restaurantId)
            ->with(['customer', 'order']);
        if ($receivableStatus) {
            $receivablesQuery->where('status', $receivableStatus);
        }
        $receivablesList = $receivablesQuery->latest()->paginate(10, ['*'], 'receivables_page')->withQueryString();

        // Customer credit list for setup tab
        $customerSearch = $request->input('customer_search');
        $customersQuery = Customer::where('restaurant_id', $restaurantId);
        if ($customerSearch) {
            $customersQuery->where(function($q) use ($customerSearch) {
                $q->where('full_name', 'like', "%{$customerSearch}%")
                  ->orWhere('phone', 'like', "%{$customerSearch}%");
            });
        }
        $customersList = $customersQuery->latest()->paginate(10, ['*'], 'customers_page')->withQueryString();

        return Inertia::render('debts/Index', [
            'stats' => [
                'total_receivable' => $totalReceivable,
                'total_payable' => $totalPayable,
                'overdue_receivable' => $overdueReceivable,
                'overdue_payable' => $overduePayable,
                'receivables_aging' => $receivablesAging,
                'payables_aging' => $payablesAging,
            ],
            'payables' => $payablesList,
            'receivables' => $receivablesList,
            'customers' => $customersList,
            'filters' => [
                'payable_status' => $payableStatus ?? '',
                'receivable_status' => $receivableStatus ?? '',
                'customer_search' => $customerSearch ?? '',
            ]
        ]);
    }

    /**
     * Repay supplier payable (Make a payment).
     */
    public function paySupplier(Request $request, AccountPayable $payable): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($payable->restaurant_id !== $user->restaurant_id, 403);

        $remaining = (float)$payable->amount - (float)$payable->paid_amount;
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $remaining],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payAmount = (float) $request->input('amount');
        $method = $request->input('payment_method');
        $notes = $request->input('notes');

        DB::transaction(function () use ($payable, $payAmount, $method, $notes, $user) {
            $newPaidAmount = (float)$payable->paid_amount + $payAmount;
            $status = $newPaidAmount >= (float)$payable->amount ? 'paid' : 'partially_paid';

            $payable->update([
                'paid_amount' => $newPaidAmount,
                'status' => $status,
                'notes' => $notes ? ($payable->notes ? $payable->notes . "\n" : '') . "[" . now()->format('d/m/Y') . "] Trả nợ: " . number_format($payAmount) . "đ. Ghi chú: " . $notes : $payable->notes,
            ]);

            // Update PurchaseOrder payment status if fully paid
            if ($status === 'paid' && $payable->purchase_order_id) {
                PurchaseOrder::where('id', $payable->purchase_order_id)->update([
                    'payment_status' => 'paid',
                ]);
            }

            // Record cash transaction if cash register is open and payment method is cash
            if ($method === 'cash') {
                $register = CashRegister::where('restaurant_id', $payable->restaurant_id)
                    ->where('branch_id', $user->branch_id)
                    ->where('status', 'open')
                    ->first();

                if ($register) {
                    CashTransaction::create([
                        'restaurant_id' => $payable->restaurant_id,
                        'branch_id' => $user->branch_id,
                        'cash_register_id' => $register->id,
                        'type' => 'out',
                        'amount' => $payAmount,
                        'source' => 'expense',
                        'reference_id' => $payable->id,
                        'reference_type' => AccountPayable::class,
                        'notes' => "Thanh toán công nợ nhà cung cấp cho PO #{$payable->purchaseOrder?->po_number}. Ghi chú: {$notes}",
                        'created_by' => $user->id,
                        'occurred_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Đã ghi nhận thanh toán nợ nhà cung cấp thành công.');
    }

    /**
     * Collect customer receivable.
     */
    public function collectCustomer(Request $request, AccountReceivable $receivable): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($receivable->restaurant_id !== $user->restaurant_id, 403);

        $remaining = (float)$receivable->amount - (float)$receivable->received_amount;
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $remaining],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $collectAmount = (float) $request->input('amount');
        $method = $request->input('payment_method');
        $notes = $request->input('notes');

        DB::transaction(function () use ($receivable, $collectAmount, $method, $notes, $user) {
            // Khóa bản ghi AccountReceivable
            $receivable = AccountReceivable::where('id', $receivable->id)->lockForUpdate()->firstOrFail();

            $newReceivedAmount = (float)$receivable->received_amount + $collectAmount;
            $status = $newReceivedAmount >= (float)$receivable->amount ? 'paid' : 'partially_paid';

            $receivable->update([
                'received_amount' => $newReceivedAmount,
                'status' => $status,
                'notes' => $notes ? ($receivable->notes ? $receivable->notes . "\n" : '') . "[" . now()->format('d/m/Y') . "] Thu nợ: " . number_format($collectAmount) . "đ. Ghi chú: " . $notes : $receivable->notes,
            ]);

            // Khóa và cập nhật dư nợ của khách hàng
            $customer = Customer::where('id', $receivable->customer_id)->lockForUpdate()->firstOrFail();
            $customer->decrement('current_debt', $collectAmount);

            // Update Order payment status if fully paid
            if ($status === 'paid' && $receivable->order_id) {
                Order::where('id', $receivable->order_id)->update([
                    'payment_status' => 'paid',
                ]);
            }

            // Record cash transaction if cash register is open and payment method is cash
            if ($method === 'cash') {
                $register = CashRegister::where('restaurant_id', $receivable->restaurant_id)
                    ->where('branch_id', $user->branch_id)
                    ->where('status', 'open')
                    ->first();

                if ($register) {
                    CashTransaction::create([
                        'restaurant_id' => $receivable->restaurant_id,
                        'branch_id' => $user->branch_id,
                        'cash_register_id' => $register->id,
                        'type' => 'in',
                        'amount' => $collectAmount,
                        'source' => 'order',
                        'reference_id' => $receivable->id,
                        'reference_type' => AccountReceivable::class,
                        'notes' => "Thu hồi công nợ khách hàng cho đơn hàng #{$receivable->order?->order_number}. Ghi chú: {$notes}",
                        'created_by' => $user->id,
                        'occurred_at' => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Đã ghi nhận thu hồi nợ của khách hàng thành công.');
    }

    /**
     * Update customer credit configurations (VIP/B2B status, credit limit).
     */
    public function updateCustomerCredit(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);
        abort_if($customer->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'is_vip' => ['required', 'boolean'],
            'is_b2b' => ['required', 'boolean'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        $customer->update([
            'is_vip' => $data['is_vip'],
            'is_b2b' => $data['is_b2b'],
            'credit_limit' => $data['credit_limit'],
        ]);

        return back()->with('success', 'Đã cập nhật cấu hình hạn mức tín dụng khách hàng.');
    }
}
