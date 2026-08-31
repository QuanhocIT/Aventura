<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalOrdersController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'status', 'payment_status', 'type', 'search', 'date_from', 'date_to']);

        $query = BillingInvoice::with(['restaurant.owner', 'subscription.plan'])
            ->where('type', $filters['type'] ?? 'payment_success')
            ->latest();

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('recipient_email', 'like', "%{$search}%")
                    ->orWhereHas('restaurant', function ($qr) use ($search) {
                        $qr->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('owner', function ($qo) use ($search) {
                                $qo->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->paginate(10)->withQueryString()->through(function (BillingInvoice $invoice) {
            $restaurant = $invoice->restaurant;
            $owner = $restaurant?->owner;
            $meta = $invoice->meta ?? [];

            return [
                'id' => $invoice->id,
                'order_number' => $invoice->invoice_number,
                'restaurant' => $restaurant?->name ?? '—',
                'restaurant_code' => $restaurant?->code ?? '',
                'plan_name' => $invoice->subscription?->plan?->name ?? 'Gói tùy chỉnh',
                'billing_cycle' => $invoice->subscription?->billing_cycle
                    ?? $invoice->subscription?->billing_meta['cycle']
                    ?? 'monthly',
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status ?? 'unpaid',
                'total_amount' => number_format($invoice->total ?? 0, 0, ',', '.'),
                'total_raw' => (float) ($invoice->total ?? 0),
                'type' => $invoice->type,
                'created_at' => $invoice->created_at?->format('d/m/Y H:i'),
                'due_on' => $invoice->due_on?->format('d/m/Y'),
                'recipient_email' => $invoice->recipient_email ?? '—',
                'buyer_name' => $meta['buyer_name'] ?? $owner?->name ?? $restaurant?->name ?? 'Chưa cập nhật',
                'buyer_phone' => $meta['buyer_phone'] ?? $owner?->phone ?? $restaurant?->phone ?? 'Chưa cập nhật',
                'buyer_email' => $invoice->recipient_email ?? $owner?->email ?? $restaurant?->email ?? 'Chưa cập nhật',
                'buyer_address' => $meta['buyer_address'] ?? $restaurant?->address ?? 'Chưa cập nhật',
                'tax_code' => $meta['tax_code'] ?? $restaurant?->tax_code ?? 'Chưa cập nhật',
            ];
        });

        $todayStats = BillingInvoice::where('type', 'payment_success')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('COUNT(*) as total_today')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue_today")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_today")
            ->selectRaw("SUM(CASE WHEN payment_status != 'paid' OR payment_status IS NULL THEN 1 ELSE 0 END) as unpaid_today")
            ->first();

        $revenueByDate = BillingInvoice::where('type', 'payment_success')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->pluck('revenue', 'date');

        $revenueTrend = collect(range(6, 0))->map(function ($daysAgo) use ($revenueByDate) {
            $date = now()->subDays($daysAgo)->toDateString();
            $label = now()->subDays($daysAgo)->format('d/m');

            return [
                'date' => $label,
                'revenue' => (float) ($revenueByDate->get($date) ?? 0),
            ];
        })->toArray();

        $planCounts = BillingInvoice::query()
            ->with('subscription.plan:id,name,code')
            ->where('type', 'payment_success')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->get()
            ->groupBy(fn (BillingInvoice $invoice) => $invoice->subscription?->plan?->code ?? 'unknown')
            ->map(fn ($invoices, $code) => [
                'code' => $code,
                'name' => $invoices->first()->subscription?->plan?->name ?? 'Không xác định',
                'count' => $invoices->count(),
            ])->values()->all();

        $stats = [
            'total_today' => (int) ($todayStats->total_today ?? 0),
            'revenue_today' => (float) ($todayStats->revenue_today ?? 0),
            'completed_today' => (int) ($todayStats->paid_today ?? 0),
            'unpaid_today' => (int) ($todayStats->unpaid_today ?? 0),
            'cancelled_today' => 0,
            'plan_counts_today' => $planCounts,
            'revenue_trend' => $revenueTrend,
        ];

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('super-admin/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }
}
