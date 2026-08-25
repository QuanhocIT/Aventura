<?php

namespace App\Http\Controllers;

use App\Models\BankStatementLine;
use App\Models\FinancialBankAccount;
use App\Models\FinancialJournalEntry;
use App\Models\Payment;
use App\Models\RestaurantBranch;
use App\Services\SepayBankService;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BankReconciliationController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext,
        private SepayBankService $sepayBankService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $user = $request->user();
        $tenantActiveBranchId = $this->tenantContext->activeBranchId();

        $selectedBranchId = $request->has('branch_id')
            ? ($request->input('branch_id') === 'all' || $request->input('branch_id') === '' ? null : $request->integer('branch_id'))
            : $tenantActiveBranchId;

        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $reconStatus = $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));

        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $baseQuery = Payment::with([
            'order' => fn ($q) => $q->with(['items.product', 'customer']),
            'branch',
            'processedBy',
            'reconciledBy',
        ])
            ->where('restaurant_id', $user->restaurant_id)
            ->where('status', 'paid')
            ->whereIn('payment_method', ['bank_transfer', 'vietqr', 'vnpay', 'momo', 'zalopay', 'card'])
            ->when($selectedBranchId !== null, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->whereBetween(DB::raw('DATE(COALESCE(paid_at, created_at))'), [$dateFrom, $dateTo]);

        // Summary calculations over baseQuery (before status and search filters)
        $summaryQuery = clone $baseQuery;
        $allPayments = $summaryQuery->get();

        $totalAmount = (float) $allPayments->sum('amount');
        $totalCount = $allPayments->count();
        $reconciledPayments = $allPayments->whereNotNull('reconciled_at');
        $reconciledAmount = (float) $reconciledPayments->sum('amount');
        $reconciledCount = $reconciledPayments->count();
        $pendingPayments = $allPayments->whereNull('reconciled_at');
        $pendingAmount = (float) $pendingPayments->sum('amount');
        $pendingCount = $pendingPayments->count();

        // Apply recon status filter
        $query = clone $baseQuery;
        if ($reconStatus === 'reconciled') {
            $query->whereNotNull('reconciled_at');
        } elseif ($reconStatus === 'unreconciled') {
            $query->whereNull('reconciled_at');
        }

        // Apply search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('transaction_code', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search): void {
                        $oq->where('order_code', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($cq) use ($search): void {
                                $cq->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $payments = $query->latest('paid_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(function (Payment $payment): array {
                $order = $payment->order;
                $customer = $order?->customer;
                $customerName = $customer?->name ?? $order?->customer_name ?? 'Khách lẻ';
                $customerPhone = $customer?->phone ?? $order?->customer_phone ?? '';

                $itemsSummary = $order && $order->items->isNotEmpty()
                    ? $order->items->take(3)->map(fn ($item) => "{$item->quantity}x ".($item->product_name ?? $item->product?->name ?? 'Món'))->join(', ').($order->items->count() > 3 ? '...' : '')
                    : '—';

                return [
                    'id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'order_code' => $order?->order_code ?? ('DH-'.$payment->order_id),
                    'paid_at' => $payment->paid_at ? $payment->paid_at->format('H:i d/m/Y') : $payment->created_at->format('H:i d/m/Y'),
                    'paid_date_raw' => $payment->paid_at ? $payment->paid_at->format('Y-m-d') : $payment->created_at->format('Y-m-d'),
                    'branch_id' => $payment->branch_id,
                    'branch_name' => $payment->branch?->name ?? 'Chi nhánh chính',
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'processed_by_name' => $payment->processedBy?->name ?? 'Thu ngân',
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method === 'vietqr' ? 'VietQR' : 'Chuyển khoản',
                    'amount' => (float) $payment->amount,
                    'transaction_code' => $payment->transaction_code ?? ($payment->meta['reference'] ?? '—'),
                    'status' => $payment->status,
                    'is_reconciled' => $payment->reconciled_at !== null,
                    'reconciled_at' => $payment->reconciled_at?->format('H:i d/m/Y'),
                    'reconciled_by_name' => $payment->reconciledBy?->name,
                    'reconciliation_note' => $payment->reconciliation_note,
                    'order' => $order ? [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'total_amount' => (float) $order->total_amount,
                        'final_amount' => (float) $order->final_amount,
                        'status' => $order->status,
                        'items' => $order->items->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->product_name ?? $item->product?->name ?? 'Món',
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'total_price' => (float) $item->total_price,
                        ]),
                    ] : null,
                    'items_summary' => $itemsSummary,
                ];
            });

        return Inertia::render('bank-reconciliation/Index', [
            'branches' => $branches,
            'accounts' => $this->bankAccountsPayload($user->restaurant_id),
            'statementLines' => $this->statementLinesPayload($user->restaurant_id),
            'filters' => [
                'branch_id' => $selectedBranchId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => $reconStatus,
                'search' => $search,
            ],
            'summary' => [
                'total_amount' => $totalAmount,
                'total_count' => $totalCount,
                'reconciled_amount' => $reconciledAmount,
                'reconciled_count' => $reconciledCount,
                'pending_amount' => $pendingAmount,
                'pending_count' => $pendingCount,
                'reconciled_rate' => $totalAmount > 0 ? round(($reconciledAmount / $totalAmount) * 100, 1) : 0,
            ],
            'payments' => $payments,
        ]);
    }

    public function reconcile(Request $request, Payment $payment): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($payment->restaurant_id === $request->user()->restaurant_id, 403);

        $payment->update([
            'reconciled_at' => now(),
            'reconciled_by' => $request->user()->id,
            'reconciliation_note' => $request->input('note'),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'reconciled_at' => $payment->reconciled_at->format('H:i d/m/Y')]);
        }

        return back()->with('success', 'Đã xác nhận đối soát tiền về ngân hàng.');
    }

    public function unreconcile(Request $request, Payment $payment): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($payment->restaurant_id === $request->user()->restaurant_id, 403);

        $payment->update([
            'reconciled_at' => null,
            'reconciled_by' => null,
            'reconciliation_note' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Đã hủy trạng thái đối soát.');
    }

    public function batchReconcile(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $updatedCount = Payment::where('restaurant_id', $request->user()->restaurant_id)
            ->whereIn('id', $data['payment_ids'])
            ->whereNull('reconciled_at')
            ->update([
                'reconciled_at' => now(),
                'reconciled_by' => $request->user()->id,
                'reconciliation_note' => $data['note'] ?? null,
            ]);

        if ($request->expectsJson()) {
            return response()->json(['updated' => $updatedCount]);
        }

        return back()->with('success', "Đã đối soát thành công {$updatedCount} đơn hàng chuyển khoản.");
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:150'],
            'account_type' => ['required', 'in:bank,card,ewallet'],
            'financial_account_code' => ['required', 'in:1121,1122,1123'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_date' => ['nullable', 'date'],
        ]);

        FinancialBankAccount::create($data + [
            'restaurant_id' => $request->user()->restaurant_id,
            'branch_id' => $this->tenantContext->activeBranchId(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã thêm tài khoản thanh toán.');
    }

    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'financial_bank_account_id' => ['required', 'integer'],
            'lines' => ['nullable', 'array', 'required_without:file', 'min:1'],
            'lines.*.transaction_date' => ['required', 'date'],
            'lines.*.value_date' => ['nullable', 'date'],
            'lines.*.external_reference' => ['nullable', 'string', 'max:180'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.amount_in' => ['nullable', 'numeric', 'min:0'],
            'lines.*.amount_out' => ['nullable', 'numeric', 'min:0'],
            'lines.*.balance' => ['nullable', 'numeric'],
            'lines.*.fee_amount' => ['nullable', 'numeric', 'min:0'],
            'file' => ['nullable', 'file', 'required_without:lines', 'mimes:csv,txt', 'max:10240'],
        ]);

        $account = FinancialBankAccount::where('restaurant_id', $request->user()->restaurant_id)
            ->findOrFail($data['financial_bank_account_id']);
        $lines = $data['lines'] ?? $this->parseCsv($request->file('file'));
        if ($lines === []) {
            throw ValidationException::withMessages([
                'file' => 'Tệp sao kê không có dòng giao dịch hợp lệ.',
            ]);
        }
        $lineRules = [
            'transaction_date' => ['required', 'date'],
            'value_date' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount_in' => ['nullable', 'numeric', 'min:0'],
            'amount_out' => ['nullable', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
        ];
        $lines = collect($lines)->values()->map(
            fn (array $row, int $index): array => Validator::make($row, $lineRules)
                ->validateWithBag('default'),
        )->all();
        $created = 0;

        DB::transaction(function () use ($lines, $account, $request, &$created): void {
            foreach ($lines as $row) {
                $amountIn = round((float) ($row['amount_in'] ?? 0), 2);
                $amountOut = round((float) ($row['amount_out'] ?? 0), 2);
                if (($amountIn > 0 && $amountOut > 0) || ($amountIn <= 0 && $amountOut <= 0)) {
                    continue;
                }

                $key = implode('|', [
                    $account->id,
                    $row['transaction_date'],
                    $row['external_reference'] ?? '',
                    $amountIn,
                    $amountOut,
                ]);
                $line = BankStatementLine::firstOrCreate(
                    ['restaurant_id' => $account->restaurant_id, 'idempotency_key' => hash('sha256', $key)],
                    [
                        'financial_bank_account_id' => $account->id,
                        'transaction_date' => $row['transaction_date'],
                        'value_date' => $row['value_date'] ?? null,
                        'external_reference' => $row['external_reference'] ?? null,
                        'description' => $row['description'] ?? null,
                        'amount_in' => $amountIn,
                        'amount_out' => $amountOut,
                        'balance' => $row['balance'] ?? null,
                        'fee_amount' => $row['fee_amount'] ?? 0,
                        'status' => 'unmatched',
                        'imported_by' => $request->user()->id,
                        'imported_at' => now(),
                        'raw_payload' => $row,
                    ],
                );
                $created += $line->wasRecentlyCreated ? 1 : 0;
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['created' => $created]);
        }

        return back()->with('success', "Đã nhập {$created} giao dịch sao kê mới.");
    }

    public function syncSepay(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'financial_bank_account_id' => ['required', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $account = FinancialBankAccount::where('restaurant_id', $request->user()->restaurant_id)
            ->findOrFail($data['financial_bank_account_id']);

        try {
            $result = $this->sepayBankService->sync(
                $account,
                $data['from_date'] ?? null,
                $data['to_date'] ?? null,
                $request->user(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'sepay' => 'Không thể đồng bộ SePay lúc này. Vui lòng kiểm tra API token, tài khoản liên kết và thử lại.',
            ]);
        }

        return back()->with('success', "Đã nhận {$result['received']} giao dịch SePay, thêm {$result['created']} giao dịch mới vào sao kê.");
    }

    public function match(Request $request, BankStatementLine $line): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        abort_if($line->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($line->status === 'unmatched', 422, 'Dòng sao kê này đã được đối soát.');
        $data = $request->validate([
            'matched_type' => ['required', 'in:payment,financial_journal_entry'],
            'matched_id' => ['required', 'integer'],
        ]);

        $model = $data['matched_type'] === 'payment'
            ? Payment::where('restaurant_id', $line->restaurant_id)->where('status', 'paid')->whereNull('reconciled_at')->findOrFail($data['matched_id'])
            : FinancialJournalEntry::withoutGlobalScopes()->where('restaurant_id', $line->restaurant_id)->findOrFail($data['matched_id']);

        if ($data['matched_type'] === 'payment' && (float) $line->amount_in <= 0) {
            return back()->withErrors(['matched_id' => 'Giao dịch thanh toán đơn hàng phải khớp với dòng tiền vào (amount_in) trên sao kê ngân hàng.']);
        }

        $amount = $line->amount_in > 0 ? (float) $line->amount_in : (float) $line->amount_out;
        $matchedAmount = $data['matched_type'] === 'payment'
            ? (float) $model->amount
            : (float) $model->total_debit;
        if (abs($matchedAmount - $amount) > 0.01) {
            return back()->withErrors(['matched_id' => 'Số tiền sao kê không khớp với thanh toán đơn hàng.']);
        }

        $line->update([
            'status' => 'matched',
            'matched_type' => $model::class,
            'matched_id' => $model->id,
        ]);

        if ($data['matched_type'] === 'payment') {
            $model->update([
                'reconciled_at' => now(),
                'reconciled_by' => $request->user()->id,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'matched']);
        }

        return back()->with('success', 'Đã đối soát giao dịch ngân hàng.');
    }

    /**
     * Danh sách tài khoản thanh toán để chọn khi nhập sao kê / đồng bộ SePay.
     *
     * @return list<array<string, mixed>>
     */
    private function bankAccountsPayload(int $restaurantId): array
    {
        return FinancialBankAccount::where('restaurant_id', $restaurantId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialBankAccount $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'bank_name' => $a->bank_name,
                'account_number_masked' => $this->maskAccountNumber($a->account_number),
                'account_holder' => $a->account_holder,
                'account_type' => $a->account_type,
                'financial_account_code' => $a->financial_account_code,
                'opening_balance' => (float) $a->opening_balance,
                'is_active' => (bool) $a->is_active,
            ])
            ->all();
    }

    /**
     * Các dòng sao kê gần đây kèm ứng viên khớp cho từng dòng chưa đối soát.
     *
     * Cẩn trọng về hiệu năng: bảng payments có hàng chục nghìn dòng chưa đối
     * soát, nên tuyệt đối không nạp danh sách ứng viên chung. match() chỉ chấp
     * nhận thanh toán có số tiền TRÙNG KHỚP (sai số 0,01đ), nên ở đây chỉ lấy
     * đúng các khoản trùng số tiền của từng dòng, gom bằng MỘT truy vấn duy
     * nhất theo whereIn thay vì mỗi dòng một truy vấn.
     *
     * @return array<string, mixed>
     */
    private function statementLinesPayload(int $restaurantId): array
    {
        $lines = BankStatementLine::where('restaurant_id', $restaurantId)
            ->with('bankAccount:id,name,bank_name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $unmatchedAmounts = $lines
            ->where('status', 'unmatched')
            ->map(fn (BankStatementLine $l): float => (float) $l->amount_in)
            ->filter(fn (float $amount): bool => $amount > 0)
            ->unique()
            ->values();

        $candidatesByAmount = collect();
        if ($unmatchedAmounts->isNotEmpty()) {
            $candidatesByAmount = Payment::where('restaurant_id', $restaurantId)
                ->where('status', 'paid')
                ->whereNull('reconciled_at')
                ->whereIn('amount', $unmatchedAmounts->all())
                ->with('order:id,order_number')
                ->orderByDesc('paid_at')
                ->limit(200)
                ->get()
                ->groupBy(fn (Payment $p): string => (string) round((float) $p->amount, 2));
        }

        return [
            'total' => BankStatementLine::where('restaurant_id', $restaurantId)->count(),
            'unmatched_count' => BankStatementLine::where('restaurant_id', $restaurantId)
                ->where('status', 'unmatched')
                ->count(),
            'lines' => $lines->map(function (BankStatementLine $l) use ($candidatesByAmount): array {
                $amountIn = (float) $l->amount_in;
                $key = (string) round($amountIn, 2);

                return [
                    'id' => $l->id,
                    'account_name' => $l->bankAccount?->name ?? '—',
                    'transaction_date' => $l->transaction_date?->format('d/m/Y'),
                    'external_reference' => $l->external_reference,
                    'description' => $l->description,
                    'amount_in' => $amountIn,
                    'amount_out' => (float) $l->amount_out,
                    'status' => $l->status,
                    // Chỉ dòng tiền VÀO mới khớp được với thanh toán đơn hàng —
                    // match() từ chối thẳng dòng tiền ra.
                    'candidates' => $l->status === 'unmatched' && $amountIn > 0
                        ? $candidatesByAmount->get($key, collect())
                            ->take(5)
                            ->map(fn (Payment $p): array => [
                                'id' => $p->id,
                                'amount' => (float) $p->amount,
                                'payment_method' => $p->payment_method,
                                'paid_at' => $p->paid_at?->format('d/m/Y H:i'),
                                'order_number' => $p->order?->order_number ?? '—',
                            ])
                            ->values()
                            ->all()
                        : [],
                ];
            })->all(),
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'manager', 'accountant', 'super_admin']) || $request->user()->hasPermissionTo('finance.view'), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin() || $request->user()->hasRole('accountant'), 403);
    }

    private function maskAccountNumber(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        return str_repeat('*', max(0, strlen($number) - 4)).substr($number, -4);
    }

    /**
     * Read the canonical CSV export format used by banks and accounting tools.
     * Header names are normalized so both English and common Vietnamese labels
     * can be imported without changing the idempotency rules.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseCsv(?UploadedFile $file): array
    {
        if (! $file) {
            return [];
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            return [];
        }

        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $headerMap = collect($headers)->mapWithKeys(function ($header, $index): array {
            $normalized = Str::of((string) $header)->ascii()->lower()->snake()->value();
            $aliases = [
                'date' => 'transaction_date',
                // 'Ngày' và 'Mô tả' là tiêu đề rất phổ biến trong tệp sao kê
                // ngân hàng Việt Nam nhưng trước đây không được nhận, khiến cả
                // tệp bị từ chối với lỗi "thiếu ngày giao dịch".
                'ngay' => 'transaction_date',
                'ngay_giao_dich' => 'transaction_date',
                'ngay_gd' => 'transaction_date',
                'transaction_date' => 'transaction_date',
                'ngay_hach_toan' => 'value_date',
                'ma_giao_dich' => 'external_reference',
                'so_tham_chieu' => 'external_reference',
                'noi_dung' => 'description',
                'dien_giai' => 'description',
                'mo_ta' => 'description',
                'ghi_chu' => 'description',
                'tien_vao' => 'amount_in',
                'ghi_co' => 'amount_in',
                'credit' => 'amount_in',
                'tien_ra' => 'amount_out',
                'ghi_no' => 'amount_out',
                'debit' => 'amount_out',
                'reference' => 'external_reference',
                'so_du' => 'balance',
                'phi' => 'fee_amount',
            ];

            return [$index => $aliases[$normalized] ?? $normalized];
        });

        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($values as $index => $value) {
                $key = $headerMap->get($index);
                if ($key !== null) {
                    $row[$key] = is_string($value) ? trim($value) : $value;
                }
            }
            foreach (['amount_in', 'amount_out', 'balance', 'fee_amount'] as $amountKey) {
                if (isset($row[$amountKey])) {
                    $row[$amountKey] = $this->parseCsvAmount($row[$amountKey]);
                }
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function parseCsvAmount(mixed $value): float
    {
        $value = trim(str_replace([' ', '₫'], '', (string) $value));
        if ($value === '') {
            return 0.0;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = strlen(substr(strrchr($value, ','), 1)) <= 2
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        return (float) $value;
    }
}
