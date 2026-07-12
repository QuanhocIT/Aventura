<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CouponWalletController extends Controller
{
    private function assertValidToken(Request $request, int $restaurantId, string $phone): void
    {
        $token = $request->query('token');
        $expectedToken = hash('sha256', $restaurantId . $phone . config('app.key'));
        if (! $token || ! hash_equals($expectedToken, $token)) {
            abort(403, 'Link truy cập không hợp lệ hoặc đã hết hạn.');
        }
    }

    public function showWallet(Request $request, int $restaurantId, string $phone)
    {
        $this->assertValidToken($request, $restaurantId, $phone);

        $restaurant = Restaurant::findOrFail($restaurantId);
        $customer = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->firstOrFail();

        $available = CustomerCoupon::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('customer_id', $customer->id)
            ->where('status', 'available')
            ->where('expires_at', '>', now())
            ->latest()
            ->get()
            ->map(fn (CustomerCoupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'discount_type' => $c->discount_type,
                'discount_value' => $c->discount_value,
                'min_order_amount' => $c->min_order_amount,
                'expires_at' => $c->expires_at->format('d/m/Y H:i'),
                'expires_at_raw' => $c->expires_at->toISOString(),
                'source' => $c->source,
            ]);

        $used = CustomerCoupon::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('customer_id', $customer->id)
            ->where('status', 'used')
            ->latest('used_at')
            ->limit(10)
            ->get()
            ->map(fn (CustomerCoupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'discount_type' => $c->discount_type,
                'discount_value' => $c->discount_value,
                'used_at' => $c->used_at?->format('d/m/Y H:i'),
            ]);

        $claimable = Promotion::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('is_approved', true)
            ->where(function ($q) { $q->whereNull('end_date')->orWhere('end_date', '>=', now()); })
            ->where(function ($q) { $q->whereNull('start_date')->orWhere('start_date', '<=', now()); })
            ->whereNotNull('code')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type,
                'value' => $p->value,
                'end_date' => $p->end_date?->format('d/m/Y'),
            ]);

        return Inertia::render('customers/CouponWallet', [
            'restaurant' => ['id' => $restaurant->id, 'name' => $restaurant->name],
            'customer' => ['id' => $customer->id, 'name' => $customer->full_name, 'phone' => $customer->phone],
            'available' => $available,
            'used' => $used,
            'claimable' => $claimable,
        ]);
    }

    public function claimCoupon(Request $request, int $restaurantId, string $phone)
    {
        $this->assertValidToken($request, $restaurantId, $phone);

        $customer = Customer::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('phone', $phone)
            ->firstOrFail();

        $data = $request->validate(['promotion_id' => ['required', 'exists:promotions,id']]);

        $promotion = Promotion::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->findOrFail($data['promotion_id']);

        $alreadyClaimed = CustomerCoupon::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('promotion_id', $promotion->id)
            ->where('source', 'claimed')
            ->exists();

        if ($alreadyClaimed) {
            return back()->with('error', 'Bạn đã nhận mã này rồi.');
        }

        CustomerCoupon::create([
            'restaurant_id' => $restaurantId,
            'customer_id' => $customer->id,
            'promotion_id' => $promotion->id,
            'code' => $promotion->code . '-' . strtoupper(Str::random(4)),
            'discount_type' => $promotion->type,
            'discount_value' => $promotion->value,
            'max_discount_amount' => $promotion->max_discount_amount,
            'min_order_amount' => $promotion->min_order_amount,
            'expires_at' => $promotion->end_date ?? now()->addDays(30),
            'status' => 'available',
            'source' => 'claimed',
        ]);

        return back()->with('success', 'Đã nhận mã khuyến mãi!');
    }
}
