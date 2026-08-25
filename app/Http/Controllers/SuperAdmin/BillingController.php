<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\CommissionLog;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Services\BillingService;
use App\Services\SuperAdmin\BillingAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly BillingAnalyticsService $billingAnalytics,
    ) {}

    // =========================================================================
    // BILLING CENTER — Index (Invoices / Webhooks / Adjustments)
    // =========================================================================

    public function index(Request $request): Response
    {
        $restaurants = Restaurant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $invoiceQuery = BillingInvoice::query()->with('restaurant')->latest();
        $webhookQuery = PaymentWebhook::query()->latest();
        $adjustmentQuery = BillingAdjustment::query()->with(['restaurant', 'creator'])->latest();

        if ($request->filled('restaurant_id')) {
            $restaurantId = (int) $request->integer('restaurant_id');
            $invoiceQuery->where('restaurant_id', $restaurantId);
            $webhookQuery->where('transaction_code', function ($query) use ($restaurantId) {
                $query->select('transaction_code')
                    ->from('restaurant_subscriptions')
                    ->where('restaurant_id', $restaurantId)
                    ->latest('id')
                    ->limit(1);
            });
            $adjustmentQuery->where('restaurant_id', $restaurantId);
        }

        if ($request->filled('status')) {
            $invoiceQuery->where('status', $request->string('status'));
            $webhookQuery->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $invoiceQuery->where('type', $request->string('type'));
            $adjustmentQuery->where('type', $request->string('type'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $invoiceQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('recipient_email', 'like', "%{$search}%");
            });

            $webhookQuery->where(function ($q) use ($search) {
                $q->where('transaction_code', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");
            });

            $adjustmentQuery->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        return Inertia::render('super-admin/billing/Index', [
            'filters' => $request->only(['restaurant_id', 'status', 'type', 'search']),
            'restaurants' => $restaurants,
            'invoices' => $invoiceQuery->paginate(5, ['*'], 'invoices_page')->withQueryString()->through(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'restaurant' => $invoice->restaurant?->name ?? '',
                'status' => $invoice->status,
                'type' => $invoice->type,
                'total' => number_format($invoice->total),
                'currency' => $invoice->currency,
                'due_on' => $invoice->due_on?->format('d/m/Y'),
                'sent_at' => $invoice->sent_at?->format('d/m/Y H:i'),
            ]),
            'webhooks' => $webhookQuery->paginate(10, ['*'], 'webhooks_page')->withQueryString()->through(fn ($webhook) => [
                'id' => $webhook->id,
                'provider' => $webhook->provider,
                'status' => $webhook->status,
                'transaction_code' => $webhook->transaction_code,
                'event_type' => $webhook->event_type,
                'processed_at' => $webhook->processed_at?->format('d/m/Y H:i'),
            ]),
            'adjustments' => $adjustmentQuery->paginate(10, ['*'], 'adjustments_page')->withQueryString()->through(fn ($adjustment) => [
                'id' => $adjustment->id,
                'restaurant' => $adjustment->restaurant?->name ?? '',
                'type' => $adjustment->type,
                'days' => $adjustment->days,
                'discount_amount' => number_format($adjustment->discount_amount),
                'reason' => $adjustment->reason,
                'creator' => $adjustment->creator?->name ?? 'System',
                'created_at' => $adjustment->created_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }

    // =========================================================================
    // INVOICE ACTIONS
    // =========================================================================

    public function resendInvoice(BillingInvoice $invoice): RedirectResponse
    {
        $this->billing->queueInvoiceEmail($invoice);

        return back()->with('success', 'Đã đưa hóa đơn vào queue gửi lại email.');
    }

    public function regenerateInvoice(BillingInvoice $invoice): RedirectResponse
    {
        $this->billing->queueInvoiceRegeneration($invoice);

        return back()->with('success', 'Đã đưa hóa đơn vào queue sinh lại file.');
    }

    public function retryWebhook(PaymentWebhook $webhook): RedirectResponse
    {
        $result = $this->billing->retryWebhook($webhook);

        return back()->with('success', $result['message'] ?? 'Đã retry webhook.');
    }

    public function downloadInvoice(BillingInvoice $invoice): HttpResponse
    {
        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            $content = Storage::disk('local')->get($invoice->pdf_path);
            $ext = pathinfo($invoice->pdf_path, PATHINFO_EXTENSION);
            $mime = $ext === 'pdf' ? 'application/pdf' : 'text/plain';

            return response($content, 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.basename($invoice->pdf_path).'"',
            ]);
        }

        $content = implode("\n", [
            'AVENTURA INVOICE',
            str_repeat('-', 40),
            'Invoice: '.$invoice->invoice_number,
            'Restaurant: '.($invoice->restaurant?->name ?? 'N/A'),
            'Type: '.$invoice->type,
            'Total: '.number_format((float) $invoice->total, 0, ',', '.').' '.$invoice->currency,
            'Status: '.$invoice->status,
            'Due: '.optional($invoice->due_on)->format('d/m/Y'),
            'Sent: '.optional($invoice->sent_at)->format('d/m/Y H:i'),
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->invoice_number.'.txt"',
        ]);
    }

    /**
     * Đánh dấu hóa đơn là "nợ xấu" (bad debt / write-off).
     * Lưu meta vào cột JSON có sẵn — không cần migration thêm.
     */
    public function writeOffInvoice(Request $request, BillingInvoice $invoice): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($invoice->status === 'written_off') {
            return back()->withErrors(['reason' => 'Hóa đơn này đã được đánh dấu nợ xấu trước đó.']);
        }

        $invoice->update([
            'status' => 'written_off',
            'meta' => array_merge($invoice->meta ?? [], [
                'written_off_at' => now()->toIso8601String(),
                'written_off_by' => $request->user()->id,
                'written_off_by_name' => $request->user()->name,
                'write_off_reason' => $request->reason,
            ]),
        ]);

        AuditLog::create([
            'restaurant_id' => $invoice->restaurant_id,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'billing_write_off',
            'subject_type' => BillingInvoice::class,
            'subject_id' => $invoice->id,
            'old_values' => ['status' => $invoice->getOriginal('status')],
            'new_values' => ['status' => 'written_off', 'reason' => $request->reason],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        DashboardController::forgetCache();

        return back()->with('success', "Đã đánh dấu hóa đơn {$invoice->invoice_number} là nợ xấu.");
    }

    // =========================================================================
    // ANALYTICS — Revenue analytics with Aging + Bad Debt
    // =========================================================================

    public function analytics(Request $request): Response
    {
        return Inertia::render('super-admin/billing/Analytics', $this->billingAnalytics->analytics(Carbon::now()));
    }

    // =========================================================================
    // FINANCIAL LEDGER — Sổ cái tổng hợp (Invoice + Adjustment + Commission)
    // =========================================================================

    public function ledger(Request $request): Response
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->subMonths(3)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfDay();
        $restaurantId = $request->filled('restaurant_id') ? (int) $request->restaurant_id : null;
        $typeFilter = $request->filled('entry_type') ? $request->entry_type : null;

        $sorted = $this->billingAnalytics->ledgerEntries($dateFrom, $dateTo, $restaurantId, $typeFilter);

        // Remove the Carbon object before pagination (Inertia can't serialize Carbon)
        $cleaned = $sorted->map(function ($item) {
            unset($item['sort_date']);

            return $item;
        });

        // Totals across the entire filtered period (before pagination)
        $totalCredit = $sorted->where('direction', 'credit')->sum('amount');
        $totalDebit = $sorted->where('direction', 'debit')->sum('amount');

        // Paginate
        $page = (int) $request->query('page', 1);
        $perPage = 30;
        $total = $cleaned->count();
        $items = $cleaned->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $restaurants = Restaurant::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('super-admin/billing/Ledger', [
            'entries' => $paginator,
            'filters' => $request->only(['date_from', 'date_to', 'restaurant_id', 'entry_type']),
            'restaurants' => $restaurants,
            'totals' => [
                'credit' => number_format($totalCredit),
                'debit' => number_format($totalDebit),
                'net' => number_format($totalCredit - $totalDebit),
                'credit_raw' => $totalCredit,
                'debit_raw' => $totalDebit,
            ],
        ]);
    }

    public function ledgerExport(Request $request): HttpResponse
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->subMonths(3)->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now()->endOfDay();
        $restaurantId = $request->filled('restaurant_id') ? (int) $request->restaurant_id : null;

        $csvRow = fn (array $fields) => implode(',', array_map(
            static fn ($v) => '"'.str_replace('"', '""', (string) ($v ?? '')).'"',
            $fields
        )).PHP_EOL;

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8 cho Excel
        $csv .= $csvRow(['Ngày', 'Loại', 'Mô tả', 'Chi tiết', 'Nhà hàng', 'Chiều', 'Số tiền (VND)', 'Trạng thái']);

        $invQ = BillingInvoice::with('restaurant')->whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($restaurantId) {
            $invQ->where('restaurant_id', $restaurantId);
        }
        $invQ->get()->each(function ($inv) use (&$csv, $csvRow) {
            $csv .= $csvRow([
                $inv->created_at->format('d/m/Y H:i'),
                'Hóa đơn',
                "INV {$inv->invoice_number}",
                $inv->type,
                $inv->restaurant?->name ?? '—',
                'Thu',
                $inv->total,
                $inv->status,
            ]);
        });

        $adjQ = BillingAdjustment::with(['restaurant', 'creator'])->whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($restaurantId) {
            $adjQ->where('restaurant_id', $restaurantId);
        }
        $adjQ->get()->each(function ($adj) use (&$csv, $csvRow) {
            $csv .= $csvRow([
                $adj->created_at->format('d/m/Y H:i'),
                'Điều chỉnh',
                $adj->reason ?? 'Không có lý do',
                "Loại: {$adj->type} | Bởi: ".($adj->creator?->name ?? 'System'),
                $adj->restaurant?->name ?? '—',
                'Chi',
                $adj->discount_amount,
                $adj->type,
            ]);
        });

        $commQ = CommissionLog::with('restaurant')->whereBetween('created_at', [$dateFrom, $dateTo]);
        if ($restaurantId) {
            $commQ->where('restaurant_id', $restaurantId);
        }
        $commQ->get()->each(function ($comm) use (&$csv, $csvRow) {
            $restaurantName = $comm->restaurant?->name ?? '—';
            $csv .= $csvRow([
                $comm->created_at->format('d/m/Y H:i'),
                'Hoa hồng',
                "Referral {$comm->commission_percentage}%",
                'Giao dịch '.number_format((float) $comm->amount).' VND',
                $restaurantName,
                'Chi',
                $comm->commission_amount,
                'paid',
            ]);
        });

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=so-cai-'.Carbon::now()->format('Ymd-His').'.csv',
        ]);
    }

    // =========================================================================
    // REVENUE RECOGNITION — Deferred Revenue Tracking
    // =========================================================================

    public function revenueRecognition(): Response
    {
        return Inertia::render('super-admin/billing/RevenueRecognition', $this->billingAnalytics->revenueRecognition(Carbon::now()));
    }

    // =========================================================================
    // DUNNING DASHBOARD — Giám sát nhắc gia hạn
    // =========================================================================

    public function dunning(Request $request): Response
    {
        return Inertia::render('super-admin/billing/Dunning', $this->billingAnalytics->dunningDashboard(Carbon::now()));
    }

    /**
     * Gửi email dunning thủ công cho một subscription.
     */
    public function sendDunning(Request $request, RestaurantSubscription $subscription): RedirectResponse
    {
        $stage = $request->input('stage', 'd7');
        $this->billing->manualSendDunning($subscription, $stage);

        return back()->with('success', 'Đã gửi email nhắc gia hạn thành công.');
    }

    /**
     * Tạm dừng dunning cho một subscription trong N ngày.
     */
    public function pauseDunning(Request $request, RestaurantSubscription $subscription): RedirectResponse
    {
        $validated = $request->validate(['days' => 'required|integer|min:1|max:30']);
        $this->billing->pauseDunning($subscription, $validated['days']);

        return back()->with('success', "Đã tạm dừng nhắc gia hạn trong {$validated['days']} ngày.");
    }

    // =========================================================================
    // SUBSCRIPTION LIFECYCLE ANALYTICS
    // =========================================================================

    public function lifecycle(): Response
    {
        return Inertia::render('super-admin/billing/Lifecycle', $this->billingAnalytics->lifecycle(Carbon::now()));
    }

    // =========================================================================
    // EXPORT CSV
    // =========================================================================

    public function exportCsv(Request $request): HttpResponse
    {
        $query = BillingInvoice::query()->with('restaurant')->latest();

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->integer('restaurant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $csvRow = fn (array $fields) => implode(',', array_map(
            static fn ($v) => '"'.str_replace('"', '""', (string) ($v ?? '')).'"',
            $fields
        )).PHP_EOL;

        $csv = $csvRow(['invoice_number', 'restaurant', 'type', 'status', 'total', 'currency', 'due_on', 'sent_at']);

        $query->chunk(200, function ($rows) use (&$csv, $csvRow) {
            foreach ($rows as $invoice) {
                $csv .= $csvRow([
                    $invoice->invoice_number,
                    $invoice->restaurant?->name ?? '',
                    $invoice->type,
                    $invoice->status,
                    (string) $invoice->total,
                    $invoice->currency,
                    optional($invoice->due_on)->format('Y-m-d'),
                    optional($invoice->sent_at)->format('Y-m-d H:i:s'),
                ]);
            }
        });

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=billing-export.csv',
        ]);
    }
}
