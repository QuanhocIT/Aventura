<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingAdjustment;
use App\Models\BillingInvoice;
use App\Models\PaymentWebhook;
use App\Models\Restaurant;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
            'invoices'    => $invoiceQuery->paginate(15, ['*'], 'invoices_page')->withQueryString()->through(fn ($invoice) => [
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
            'webhooks'    => $webhookQuery->paginate(15, ['*'], 'webhooks_page')->withQueryString()->through(fn ($webhook) => [
                'id'               => $webhook->id,
                'provider'         => $webhook->provider,
                'status'           => $webhook->status,
                'transaction_code' => $webhook->transaction_code,
                'event_type'       => $webhook->event_type,
                'processed_at'     => $webhook->processed_at?->format('d/m/Y H:i'),
            ]),
            'adjustments' => $adjustmentQuery->paginate(15, ['*'], 'adjustments_page')->withQueryString()->through(fn ($adjustment) => [
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
