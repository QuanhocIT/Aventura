<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalOrdersController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'status', 'payment_status', 'search', 'date_from', 'date_to']);

        $query = \App\Models\BillingInvoice::with(['restaurant.owner', 'subscription.plan'])->latest();

        if (!empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['search'])) {
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

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->paginate(10)->withQueryString()->through(function (\App\Models\BillingInvoice $invoice) {
            $restaurant = $invoice->restaurant;
            $owner = $restaurant?->owner;
            $meta = $invoice->meta ?? [];

            return [
                'id' => $invoice->id,
                'order_number' => $invoice->invoice_number,
                'restaurant' => $restaurant?->name ?? '—',
                'restaurant_code' => $restaurant?->code ?? '',
                'plan_name' => $invoice->subscription?->plan?->name ?? 'Gói tùy chỉnh',
                'billing_cycle' => $invoice->subscription?->billing_meta['cycle'] ?? 'monthly',
                'status' => $invoice->status,
                'total_amount' => number_format($invoice->total ?? 0, 0, ',', '.'),
                'total_raw' => (float)($invoice->total ?? 0),
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

        $today = now()->toDateString();
        
        $totalToday = \App\Models\BillingInvoice::whereDate('created_at', $today)->count();
        $revenueToday = (float)\App\Models\BillingInvoice::whereDate('created_at', $today)->where('status', 'paid')->sum('total');
        $paidToday = \App\Models\BillingInvoice::whereDate('created_at', $today)->where('status', 'paid')->count();
        $unpaidToday = \App\Models\BillingInvoice::whereDate('created_at', $today)->where('status', 'unpaid')->count();

        $revenueTrend = collect(range(6, 0))->map(function($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            $label = now()->subDays($daysAgo)->format('d/m');
            $revenue = \App\Models\BillingInvoice::whereDate('created_at', $date)
                ->where('status', 'paid')
                ->sum('total') ?? 0;
            return [
                'date' => $label,
                'revenue' => (float)$revenue,
            ];
        })->toArray();

        $starterCount = \App\Models\BillingInvoice::whereHas('subscription.plan', function($q) {
            $q->where('code', 'starter');
        })->where('status', 'paid')->count();

        $proCount = \App\Models\BillingInvoice::whereHas('subscription.plan', function($q) {
            $q->where('code', 'pro');
        })->where('status', 'paid')->count();

        $enterpriseCount = \App\Models\BillingInvoice::whereHas('subscription.plan', function($q) {
            $q->where('code', 'enterprise');
        })->where('status', 'paid')->count();

        $customCount = \App\Models\BillingInvoice::where(function($q) {
            $q->whereNull('restaurant_subscription_id')
              ->orWhereDoesntHave('subscription.plan');
        })->where('status', 'paid')->count();

        $stats = [
            'total_today' => $totalToday,
            'revenue_today' => $revenueToday,
            'completed_today' => $paidToday,
            'cancelled_today' => $unpaidToday,
            
            'pos_today' => $starterCount,
            'qr_today' => $proCount,
            'online_today' => $enterpriseCount,
            'delivery_today' => $customCount,
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
