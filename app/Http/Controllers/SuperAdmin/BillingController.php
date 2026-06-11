<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

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
            'filters'     => $request->only(['restaurant_id', 'status', 'type', 'search']),
            'restaurants' => $restaurants,
            'invoices'    => $invoiceQuery->paginate(10, ['*'], 'invoices_page')->withQueryString()->through(fn ($invoice) => [
                'id'             => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'restaurant'     => $invoice->restaurant?->name ?? '',
                'status'         => $invoice->status,
                'type'           => $invoice->type,
                'total'          => number_format($invoice->total),
                'currency'       => $invoice->currency,
                'due_on'         => $invoice->due_on?->format('d/m/Y'),
                'sent_at'        => $invoice->sent_at?->format('d/m/Y H:i'),
            ]),
            'webhooks'    => $webhookQuery->paginate(10, ['*'], 'webhooks_page')->withQueryString()->through(fn ($webhook) => [
                'id'               => $webhook->id,
                'provider'         => $webhook->provider,
                'status'           => $webhook->status,
                'transaction_code' => $webhook->transaction_code,
                'event_type'       => $webhook->event_type,
                'processed_at'     => $webhook->processed_at?->format('d/m/Y H:i'),
            ]),
            'adjustments' => $adjustmentQuery->paginate(10, ['*'], 'adjustments_page')->withQueryString()->through(fn ($adjustment) => [
                'id'              => $adjustment->id,
                'restaurant'      => $adjustment->restaurant?->name ?? '',
                'type'            => $adjustment->type,
                'days'            => $adjustment->days,
                'discount_amount' => number_format($adjustment->discount_amount),
                'reason'          => $adjustment->reason,
                'creator'         => $adjustment->creator?->name ?? 'System',
                'created_at'      => $adjustment->created_at?->format('d/m/Y H:i'),
            ]),
        ]);
    }

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
        // Nếu có PDF thật thì stream, ngược lại tạo text download
        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            $content = Storage::disk('local')->get($invoice->pdf_path);
            $ext = pathinfo($invoice->pdf_path, PATHINFO_EXTENSION);
            $mime = $ext === 'pdf' ? 'application/pdf' : 'text/plain';

            return response($content, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($invoice->pdf_path) . '"',
            ]);
        }

        // Fallback: tạo text
        $content = implode("\n", [
            'AVENTURA INVOICE',
            str_repeat('-', 40),
            'Invoice: ' . $invoice->invoice_number,
            'Restaurant: ' . ($invoice->restaurant?->name ?? 'N/A'),
            'Type: ' . $invoice->type,
            'Total: ' . number_format((float) $invoice->total, 0, ',', '.') . ' ' . $invoice->currency,
            'Status: ' . $invoice->status,
            'Due: ' . optional($invoice->due_on)->format('d/m/Y'),
            'Sent: ' . optional($invoice->sent_at)->format('d/m/Y H:i'),
        ]);

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="invoice-' . $invoice->invoice_number . '.txt"',
        ]);
    }

    public function analytics(Request $request): Response
    {
        $now = Carbon::now();

        // MRR: tổng giá từ subscription active billing_cycle=monthly
        $mrr = (float) RestaurantSubscription::query()
            ->where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('price');

        // Yearly subs → chia 12 để tính MRR tương đương
        $mrrFromYearly = (float) RestaurantSubscription::query()
            ->where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum(DB::raw('price / 12'));

        $mrr += $mrrFromYearly;
        $arr = $mrr * 12;

        // Active paying restaurants (có subscription active, không phải trial)
        $activeCount = RestaurantSubscription::query()
            ->where('status', 'active')
            ->whereHas('plan', fn($q) => $q->where('price', '>', 0))
            ->distinct('restaurant_id')
            ->count('restaurant_id');

        // Churn: restaurants went expired in last 30 days
        $churnedCount = Restaurant::query()
            ->where('status', 'expired')
            ->whereDate('subscription_ends_at', '>=', $now->copy()->subDays(30)->toDateString())
            ->whereDate('subscription_ends_at', '<', $now->toDateString())
            ->count();

        $churnRate = $activeCount > 0 ? round(($churnedCount / max($activeCount, 1)) * 100, 1) : 0;

        // Doanh thu 12 tháng gần nhất
        $monthlyRevenue = BillingInvoice::query()
            ->where('type', 'payment_success')
            ->where('status', '!=', 'pending')
            ->where('created_at', '>=', $now->copy()->subMonths(12)->startOfMonth())
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get()
            ->map(fn ($row) => [
                'label'   => Carbon::createFromDate($row->year, $row->month, 1)->format('M Y'),
                'revenue' => (float) $row->revenue,
                'count'   => (int) $row->count,
            ])
            ->values()
            ->toArray();

        // Sắp hết hạn
        $expiring7  = Restaurant::whereDate('subscription_ends_at', '>=', $now->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(7)->toDateString())
            ->where('status', 'active')
            ->count();
        $expiring14 = Restaurant::whereDate('subscription_ends_at', '>', $now->copy()->addDays(7)->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(14)->toDateString())
            ->where('status', 'active')
            ->count();
        $expiring30 = Restaurant::whereDate('subscription_ends_at', '>', $now->copy()->addDays(14)->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(30)->toDateString())
            ->where('status', 'active')
            ->count();

        // Doanh thu theo gói
        $revenueByPlan = DB::table('billing_invoices')
            ->join('restaurant_subscriptions', 'billing_invoices.restaurant_subscription_id', '=', 'restaurant_subscriptions.id')
            ->join('subscription_plans', 'restaurant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('billing_invoices.type', 'payment_success')
            ->where('billing_invoices.status', '!=', 'pending')
            ->select(
                'subscription_plans.name as plan',
                DB::raw('SUM(billing_invoices.total) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'plan'    => $row->plan,
                'revenue' => (float) $row->revenue,
                'count'   => (int) $row->count,
            ])
            ->toArray();

        // Webhook success rate
        $totalWebhooks = PaymentWebhook::count();
        $processedWebhooks = PaymentWebhook::where('status', 'processed')->count();
        $webhookSuccessRate = $totalWebhooks > 0 ? round(($processedWebhooks / $totalWebhooks) * 100, 1) : 100;

        // Restaurants sắp hết hạn (danh sách)
        $expiringList = Restaurant::query()
            ->where('status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->whereDate('subscription_ends_at', '>=', $now->toDateString())
            ->whereDate('subscription_ends_at', '<=', $now->copy()->addDays(30)->toDateString())
            ->with('plan:id,name')
            ->orderBy('subscription_ends_at')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'plan'        => $r->plan?->name ?? 'N/A',
                'expires_on'  => Carbon::parse($r->subscription_ends_at)->format('d/m/Y'),
                'days_left'   => $now->diffInDays(Carbon::parse($r->subscription_ends_at), false),
            ]);

        return Inertia::render('super-admin/billing/Analytics', [
            'kpis' => [
                'mrr'                  => number_format($mrr, 0, ',', '.'),
                'arr'                  => number_format($arr, 0, ',', '.'),
                'active_count'         => $activeCount,
                'churn_rate'           => $churnRate,
                'churned_30d'          => $churnedCount,
                'expiring_7d'          => $expiring7,
                'expiring_14d'         => $expiring14,
                'expiring_30d'         => $expiring30,
                'webhook_success_rate' => $webhookSuccessRate,
            ],
            'monthly_revenue' => $monthlyRevenue,
            'revenue_by_plan' => $revenueByPlan,
            'expiring_list'   => $expiringList,
        ]);
    }

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
            static fn ($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"',
            $fields
        )) . PHP_EOL;

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
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=billing-export.csv',
        ]);
    }
}
