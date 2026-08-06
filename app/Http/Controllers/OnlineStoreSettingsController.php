<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesRestaurantSettings;
use App\Models\OnlineStoreConfig;
use App\Services\PaymentGatewayService;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnlineStoreSettingsController extends Controller
{
    use AuthorizesRestaurantSettings;

    public function index(Request $request): Response
    {
        $this->authorizeRestaurantSettings($request);
        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'qr_ordering')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'qr_ordering',
                'feature_label' => 'Đặt hàng Online',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $branches = $restaurant?->branches()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']) ?? collect();

        $config = OnlineStoreConfig::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->first();

        if (! $config) {
            $restaurant = $request->user()->restaurant;
            $config = new OnlineStoreConfig([
                'restaurant_id' => $restaurantId,
                'is_active' => false,
                'slug' => Str::slug($restaurant?->name ?? 'store').'-'.Str::lower(Str::random(4)),
                'enable_takeaway' => true,
                'enable_delivery' => true,
                'enable_preorder' => false,
                'min_order_amount' => 0,
                'delivery_fee_per_km' => 5000,
                'delivery_base_fee' => 15000,
                'max_delivery_km' => 10,
                'accepted_payments' => ['bank_transfer'],
                'branch_id' => $branches->first()?->id,
            ]);
        }

        $storeUrl = $config->exists ? url("/order/{$config->slug}") : null;

        return Inertia::render('online-store/Index', [
            'config' => $config,
            'storeUrl' => $storeUrl,
            'branches' => $branches,
            'paymentMethods' => collect([['key' => 'cod', 'name' => 'Thanh toán khi nhận hàng']])
                ->merge(app(PaymentGatewayService::class)->getConfiguredGateways())
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeRestaurantSettings($request);
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'delivery_fee_per_km' => ['required', 'numeric', 'min:0'],
            'delivery_base_fee' => ['required', 'numeric', 'min:0'],
            'max_delivery_km' => ['required', 'numeric', 'min:1', 'max:50'],
            'enable_takeaway' => ['required', 'boolean'],
            'enable_delivery' => ['required', 'boolean'],
            'enable_preorder' => ['required', 'boolean'],
            'accepted_payments' => ['required', 'array', 'min:1'],
            'accepted_payments.*' => [
                'string',
                Rule::in(array_merge(['cod'], array_column(app(PaymentGatewayService::class)->getConfiguredGateways(), 'key'))),
            ],
            'operating_hours' => ['nullable', 'array'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $restaurantId = $request->user()->restaurant_id;

        $branch = null;
        if ($data['branch_id'] ?? null) {
            $branch = $request->user()->restaurant?->branches()
                ->where('status', 'active')
                ->whereKey($data['branch_id'])
                ->first();

            if (! $branch) {
                return back()->withErrors(['branch_id' => 'Chi nhánh không thuộc nhà hàng hoặc đang ngừng hoạt động.']);
            }
        } elseif (($request->user()->restaurant?->branches()->where('status', 'active')->count() ?? 0) > 1) {
            return back()->withErrors(['branch_id' => 'Vui lòng chọn chi nhánh phục vụ cửa hàng online.']);
        }

        $existing = OnlineStoreConfig::withoutGlobalScopes()
            ->where('slug', $data['slug'])
            ->where('restaurant_id', '!=', $restaurantId)
            ->exists();

        if ($existing) {
            return back()->withErrors(['slug' => 'Đường dẫn này đã được sử dụng bởi nhà hàng khác.']);
        }

        OnlineStoreConfig::withoutGlobalScopes()->updateOrCreate(
            ['restaurant_id' => $restaurantId],
            $data
        );

        return back()->with('success', 'Đã lưu cấu hình cửa hàng online.');
    }
}
