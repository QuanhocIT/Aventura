<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'rating', 'search']);

        $query = CustomerFeedback::withoutGlobalScopes();

        if (!empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (!empty($filters['search'])) {
            $query->where('comment', 'like', "%{$filters['search']}%");
        }

        $restaurantCache = Restaurant::pluck('name', 'id')->toArray();
        $restaurantCodeCache = Restaurant::pluck('code', 'id')->toArray();

        $feedbacks = $query->latest()->paginate(20)->through(function (CustomerFeedback $f) use ($restaurantCache, $restaurantCodeCache) {
            $customerName = null;
            if ($f->customer_id) {
                $customer = Customer::withoutGlobalScopes()->find($f->customer_id);
                $customerName = $customer?->name;
            }

            $orderNumber = null;
            if ($f->order_id) {
                $order = Order::withoutGlobalScopes()->find($f->order_id);
                $orderNumber = $order?->order_number;
            }

            return [
                'id' => $f->id,
                'customer_name' => $customerName ?? ($f->is_anonymous ? 'Ẩn danh' : '—'),
                'restaurant' => $restaurantCache[$f->restaurant_id] ?? '—',
                'restaurant_code' => $restaurantCodeCache[$f->restaurant_id] ?? '',
                'order_number' => $orderNumber,
                'rating' => $f->rating,
                'comment' => $f->comment,
                'is_anonymous' => $f->is_anonymous,
                'created_at' => $f->created_at?->format('d/m/Y H:i'),
            ];
        });

        $stats = [
            'total' => CustomerFeedback::withoutGlobalScopes()->count(),
            'avg_rating' => round(CustomerFeedback::withoutGlobalScopes()->avg('rating') ?? 0, 1),
            'positive' => CustomerFeedback::withoutGlobalScopes()->where('rating', '>=', 4)->count(),
            'negative' => CustomerFeedback::withoutGlobalScopes()->where('rating', '<=', 2)->count(),
        ];

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('super-admin/feedback/Index', [
            'feedbacks' => $feedbacks,
            'stats' => $stats,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }
}
