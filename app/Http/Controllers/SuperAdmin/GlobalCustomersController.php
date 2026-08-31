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

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('full_name', 'like', "%{$filters['search']}%")
                    ->orWhere('phone', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (! empty($filters['vip'])) {
            $query->where('is_vip', true);
        }

        // Reuse the restaurant options for row labels instead of issuing two
        // separate pluck queries and loading the same list again below.
        $restaurants = Restaurant::select('id', 'name', 'code')->orderBy('name')->get();
        $restaurantCache = $restaurants->pluck('name', 'id')->all();
        $restaurantCodeCache = $restaurants->pluck('code', 'id')->all();

        $canViewPii = $request->user()?->isSuperAdmin() === true;
        $customers = $query->latest()->paginate(20)->through(fn (Customer $c) => [
            'id' => $c->id,
            'name' => $c->full_name ?? '—',
            'phone' => $canViewPii ? $c->phone : $this->maskPhone($c->phone),
            'email' => $canViewPii ? $c->email : $this->maskEmail($c->email),
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

        return Inertia::render('super-admin/customers/Index', [
            'customers' => $customers,
            'stats' => $stats,
            'restaurants' => $restaurants,
            'filters' => $filters,
        ]);
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        return mb_substr($phone, 0, 3).'****'.mb_substr($phone, -2);
    }

    private function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
