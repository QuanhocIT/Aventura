<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountPayablePayment;
use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\CashRegister;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Services\CashPostingService;
use App\Services\FinancialPostingService;
use App\Services\QuotaService;
use App\Support\Tenant\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private CashPostingService $cashPostingService,
        private FinancialPostingService $financialPostingService,
    ) {}

    /**
     * Display the debts dashboard & list of accounts payable/receivable.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $restaurant = $user->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'inventory_basic')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'inventory_basic',
                'feature_label' => 'Quản lý Công nợ',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $user->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId()
            ?? ($user->isOwner() ? null : $user->assignedBranchId());

        // Statistics
        $totalReceivable = (float) AccountReceivable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount - received_amount) as total')
            ->value('total') ?? 0.0;

        $totalPayable = (float) AccountPayable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0.0;

        $today = now()->startOfDay();

        $overdueReceivable = (float) AccountReceivable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->selectRaw('SUM(amount - received_amount) as total')
            ->value('total') ?? 0.0;

        $overduePayable = (float) AccountPayable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->selectRaw('SUM(amount - paid_amount) as total')
            ->value('total') ?? 0.0;

        // Receivables Aging Analysis
        $receivables = AccountReceivable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
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
            $remaining = (float) $ar->amount - (float) $ar->received_amount;
            if ($remaining <= 0) {
                continue;
            }

            $dueDate = Carbon::parse($ar->due_date)->startOfDay();
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
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
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
            $remaining = (float) $ap->amount - (float) $ap->paid_amount;
            if ($remaining <= 0) {
                continue;
            }

            $dueDate = Carbon::parse($ap->due_date)->startOfDay();
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
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['supplier', 'purchaseOrder']);
        if ($payableStatus) {
            $payablesQuery->where('status', $payableStatus);
        }
        $payablesList = $payablesQuery->latest()->paginate(10, ['*'], 'payables_page')->withQueryString();

        // Pagination and Filters for Receivables List
        $receivableStatus = $request->input('receivable_status');
        $receivablesQuery = AccountReceivable::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['customer', 'order']);
        if ($receivableStatus) {
            $receivablesQuery->where('status', $receivableStatus);
        }
        $receivablesList = $receivablesQuery->latest()->paginate(10, ['*'], 'receivables_page')->withQueryString();

        // Customer credit list for setup tab
        $customerSearch = $request->input('customer_search');
        $customersQuery = Customer::where('restaurant_id', $restaurantId);
        if ($customerSearch) {
            $customersQuery->where(function ($q) use ($customerSearch) {
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
            ],
            'canManageDebt' => $user->isOwner() || $user->isSuperAdmin(),
        ]);
    }

    /**
     * Repay supplier payable (Make a payment).
     */
    public function paySupplier(Request $request, AccountPayable $payable): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($payable->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->resolveOperationalBranch($user);
        abort_if($payable->purchaseOrder?->branch_id !== null && (int) $payable->purchaseOrder->branch_id !== $branchId, 403);

        $remaining = (float) $payable->amount - (float) $payable->paid_amount;
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payAmount = (float) $request->input('amount');
        $method = $request->input('payment_method');
        $notes = $request->input('notes');

        try {
            DB::transaction(function () use ($payable, $payAmount, $method, $notes, $user, $branchId, $request) {
                $lockedPayable = AccountPayable::where('id', $payable->id)->lockForUpdate()->firstOrFail();
                $rem = (float) $lockedPayable->amount - (float) $lockedPayable->paid_amount;
                if ($payAmount > $rem) {
                    throw new \Exception('Số tiền trả nợ vượt quá số dư nợ còn lại ('.number_format($rem).'đ).');
                }

                $newPaidAmount = (float) $lockedPayable->paid_amount + $payAmount;
                $status = $newPaidAmount >= (float) $lockedPayable->amount ? 'paid' : 'partially_paid';

                $lockedPayable->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $status,
                    'notes' => $notes ? ($lockedPayable->notes ? $lockedPayable->notes."\n" : '').'['.now()->format('d/m/Y').'] Trả nợ: '.number_format($payAmount).'đ. Ghi chú: '.$notes : $lockedPayable->notes,
                ]);

                AccountPayablePayment::firstOrCreate(
                    [
                        'restaurant_id' => $lockedPayable->restaurant_id,
                        'idempotency_key' => 'payable-payment:'.$lockedPayable->id.':'.$newPaidAmount,
                    ],
                    [
                        'account_payable_id' => $lockedPayable->id,
                        'branch_id' => $branchId,
                        'amount' => $payAmount,
                        'payment_method' => $method,
                        'payment_reference' => $request->input('payment_reference'),
                        'paid_at' => now(),
                        'created_by' => $user->id,
                        'notes' => $notes,
                    ],
                );

                // Update PurchaseOrder payment status if fully paid
                if ($status === 'paid' && $lockedPayable->purchase_order_id) {
                    PurchaseOrder::where('id', $lockedPayable->purchase_order_id)->update([
                        'payment_status' => 'paid',
                    ]);
                }

                if ($method !== 'cash') {
                    $this->financialPostingService->post([
                        'restaurant_id' => $lockedPayable->restaurant_id,
                        'branch_id' => $branchId,
                        'entry_date' => today(),
                        'source_type' => AccountPayable::class,
                        'source_id' => $lockedPayable->id,
                        'idempotency_key' => 'payable-payment:'.$lockedPayable->id.':'.$newPaidAmount,
                        'description' => 'Thanh toán công nợ nhà cung cấp #'.$lockedPayable->id,
                        'created_by' => $user->id,
                        'posted_by' => $user->id,
                        'metadata' => ['payment_method' => $method],
                        'lines' => [
                            ['account' => '3311', 'debit' => $payAmount, 'credit' => 0],
                            ['account' => '1121', 'debit' => 0, 'credit' => $payAmount],
                        ],
                    ]);
                }

                // Record cash transaction if cash register is open and payment method is cash
                if ($method === 'cash') {
                    $register = CashRegister::where('restaurant_id', $lockedPayable->restaurant_id)
                        ->where('branch_id', $branchId)
                        ->where('status', 'open')
                        ->first();

                    if ($register) {
                        $this->cashPostingService->record([
                            'restaurant_id' => $lockedPayable->restaurant_id,
                            'branch_id' => $branchId,
                            'cash_register_id' => $register->id,
                            'type' => 'out',
                            'amount' => $payAmount,
                            'source' => 'expense',
                            'idempotency_key' => 'payable-payment:'.$lockedPayable->id.':'.$newPaidAmount,
                            'debit_account' => '3311',
                            'credit_account' => '1111',
                            'journal_source_type' => AccountPayable::class,
                            'journal_source_id' => $lockedPayable->id,
                            'reference_id' => $lockedPayable->id,
                            'reference_type' => AccountPayable::class,
                            'notes' => "Thanh toán công nợ nhà cung cấp cho PO #{$lockedPayable->purchaseOrder?->po_number}. Ghi chú: {$notes}",
                            'created_by' => $user->id,
                            'occurred_at' => now(),
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận thanh toán nợ nhà cung cấp thành công.');
    }

    /**
     * Collect customer receivable.
     */
    public function collectCustomer(Request $request, AccountReceivable $receivable): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($receivable->restaurant_id !== $user->restaurant_id, 403);
        $branchId = $this->resolveOperationalBranch($user);
        abort_if($receivable->order?->branch_id !== null && (int) $receivable->order->branch_id !== $branchId, 403);

        $remaining = (float) $receivable->amount - (float) $receivable->received_amount;
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $collectAmount = (float) $request->input('amount');
        $method = $request->input('payment_method');
        $notes = $request->input('notes');

        try {
            DB::transaction(function () use ($receivable, $collectAmount, $method, $notes, $user, $branchId, $request) {
                // Khóa bản ghi AccountReceivable
                $lockedReceivable = AccountReceivable::where('id', $receivable->id)->lockForUpdate()->firstOrFail();
                $rem = (float) $lockedReceivable->amount - (float) $lockedReceivable->received_amount;
                if ($collectAmount > $rem) {
                    throw new \Exception('Số tiền thu nợ vượt quá số dư nợ còn lại ('.number_format($rem).'đ).');
                }

                $newReceivedAmount = (float) $lockedReceivable->received_amount + $collectAmount;
                $status = $newReceivedAmount >= (float) $lockedReceivable->amount ? 'paid' : 'partially_paid';

                $lockedReceivable->update([
                    'received_amount' => $newReceivedAmount,
                    'status' => $status,
                    'notes' => $notes ? ($lockedReceivable->notes ? $lockedReceivable->notes."\n" : '').'['.now()->format('d/m/Y').'] Thu nợ: '.number_format($collectAmount).'đ. Ghi chú: '.$notes : $lockedReceivable->notes,
                ]);

                // Khóa và cập nhật dư nợ của khách hàng
                $customer = Customer::where('id', $lockedReceivable->customer_id)->lockForUpdate()->firstOrFail();
                $customer->decrement('current_debt', $collectAmount);

                // Update Order payment status if fully paid
                if ($status === 'paid' && $lockedReceivable->order_id) {
                    Order::where('id', $lockedReceivable->order_id)->update([
                        'payment_status' => 'paid',
                    ]);
                }

                AccountReceivablePayment::firstOrCreate(
                    [
                        'restaurant_id' => $lockedReceivable->restaurant_id,
                        'idempotency_key' => 'receivable-collection:'.$lockedReceivable->id.':'.$newReceivedAmount,
                    ],
                    [
                        'account_receivable_id' => $lockedReceivable->id,
                        'branch_id' => $branchId,
                        'amount' => $collectAmount,
                        'payment_method' => $method,
                        'payment_reference' => $request->input('payment_reference'),
                        'received_at' => now(),
                        'created_by' => $user->id,
                        'notes' => $notes,
                    ],
                );

                if ($method !== 'cash') {
                    $this->financialPostingService->post([
                        'restaurant_id' => $lockedReceivable->restaurant_id,
                        'branch_id' => $branchId,
                        'entry_date' => today(),
                        'source_type' => AccountReceivable::class,
                        'source_id' => $lockedReceivable->id,
                        'idempotency_key' => 'receivable-collection:'.$lockedReceivable->id.':'.$newReceivedAmount,
                        'description' => 'Thu hồi công nợ khách hàng #'.$lockedReceivable->id,
                        'created_by' => $user->id,
                        'posted_by' => $user->id,
                        'metadata' => ['payment_method' => $method],
                        'lines' => [
                            ['account' => '1121', 'debit' => $collectAmount, 'credit' => 0],
                            ['account' => '1311', 'debit' => 0, 'credit' => $collectAmount],
                        ],
                    ]);
                }

                // Record cash transaction if cash register is open and payment method is cash
                if ($method === 'cash') {
                    $register = CashRegister::where('restaurant_id', $lockedReceivable->restaurant_id)
                        ->where('branch_id', $branchId)
                        ->where('status', 'open')
                        ->first();

                    if ($register) {
                        $this->cashPostingService->record([
                            'restaurant_id' => $lockedReceivable->restaurant_id,
                            'branch_id' => $branchId,
                            'cash_register_id' => $register->id,
                            'type' => 'in',
                            'amount' => $collectAmount,
                            'source' => 'order',
                            'idempotency_key' => 'receivable-collection:'.$lockedReceivable->id.':'.$newReceivedAmount,
                            'debit_account' => '1111',
                            'credit_account' => '1311',
                            'journal_source_type' => AccountReceivable::class,
                            'journal_source_id' => $lockedReceivable->id,
                            'reference_id' => $lockedReceivable->id,
                            'reference_type' => AccountReceivable::class,
                            'notes' => "Thu hồi công nợ khách hàng cho đơn hàng #{$lockedReceivable->order?->order_number}. Ghi chú: {$notes}",
                            'created_by' => $user->id,
                            'occurred_at' => now(),
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận thu hồi nợ của khách hàng thành công.');
    }

    /**
     * Update customer credit configurations (VIP/B2B status, credit limit).
     */
    public function updateCustomerCredit(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($customer->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'is_vip' => ['required', 'boolean'],
            'is_b2b' => ['required', 'boolean'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
        ]);

        $maxManagerLimit = 20000000.0; // 20,000,000 VND
        if ((float) $data['credit_limit'] > $maxManagerLimit && ! $user->hasAnyRole(['owner', 'super_admin'])) {
            return back()->withErrors(['credit_limit' => 'Hạn mức công nợ vượt ngưỡng 20.000.000đ yêu cầu phê duyệt từ Chủ nhà hàng.']);
        }

        $customer->update([
            'is_vip' => $data['is_vip'],
            'is_b2b' => $data['is_b2b'],
            'credit_limit' => $data['credit_limit'],
        ]);

        return back()->with('success', 'Đã cập nhật cấu hình hạn mức tín dụng khách hàng.');
    }

    private function resolveOperationalBranch($user): int
    {
        $branchId = $this->tenantContext->activeBranchId()
            ?? ($user->isOwner() ? $user->assignedBranchId() : null);

        abort_if($branchId === null, 422, 'Hãy chọn chi nhánh hiện tại trước khi ghi nhận nghiệp vụ công nợ.');
        abort_unless($user->canAccessBranch($branchId), 403);

        return $branchId;
    }
}
