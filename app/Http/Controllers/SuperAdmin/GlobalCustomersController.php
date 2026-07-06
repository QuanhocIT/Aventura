<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalCustomersController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['restaurant_id', 'search', 'vip']);

        $query = Customer::withoutGlobalScopes();

        if (!empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('full_name', 'like', "%{$filters['search']}%")
                  ->orWhere('phone', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['vip'])) {
            $query->where('is_vip', true);
        }

        $restaurantCache = Restaurant::pluck('name', 'id')->toArray();
        $restaurantCodeCache = Restaurant::pluck('code', 'id')->toArray();

        $customers = $query->latest()->paginate(20)->through(fn (Customer $c) => [
            'id' => $c->id,
            'name' => $c->full_name ?? '—',
            'phone' => $c->phone,
            'email' => $c->email,
            'restaurant' => $restaurantCache[$c->restaurant_id] ?? '—',
            'restaurant_code' => $restaurantCodeCache[$c->restaurant_id] ?? '',
            'is_vip' => $c->is_vip,
            'loyalty_points' => $c->loyalty_points ?? 0,
            'total_spent' => (float) ($c->total_spent ?? 0),
            'last_order_at' => $c->last_order_at,
            'created_at' => $c->created_at?->format('d/m/Y'),
        ]);

        $stats = [
            'total' => Customer::withoutGlobalScopes()->count(),
            'vip' => Customer::withoutGlobalScopes()->where('is_vip', true)->count(),
            'new_this_month' => Customer::withoutGlobalScopes()->where('created_at', '>=', now()->startOfMonth())->count(),
            'has_spent' => Customer::withoutGlobalScopes()->where('total_spent', '>', 0)->count(),
        ];

        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();

        return Inertia::render('super-admin/customers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }
}
