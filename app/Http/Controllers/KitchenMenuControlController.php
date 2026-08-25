<?php

namespace App\Http\Controllers;

use App\Events\Customer\ProductStockUpdated;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Notifications\KitchenMenuUnavailableNotification;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KitchenMenuControlController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);

        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 403, 'Bếp phải được mở trong một chi nhánh cụ thể.');
        $branchName = (string) (RestaurantBranch::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereKey($branchId)
            ->value('name') ?? 'Chi nhánh hiện tại');

        $products = $this->branchProducts($user->restaurant_id, (int) $branchId)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => $this->serializeProduct($product))
            ->values();

        $categories = ProductCategory::where('restaurant_id', $user->restaurant_id)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('status', 'active')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();

        return Inertia::render('kitchen/MenuControl', [
            'products' => $products,
            'categories' => $categories,
            'branchName' => $branchName,
            'today' => now()->toDateString(),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('manage_kitchen'), 403);

        $branchId = app(TenantContext::class)->activeBranchId();
        abort_if($branchId === null, 403, 'Bếp phải được mở trong một chi nhánh cụ thể.');
        $branchName = (string) (RestaurantBranch::withoutGlobalScopes()
            ->where('restaurant_id', $user->restaurant_id)
            ->whereKey($branchId)
            ->value('name') ?? 'Chi nhánh hiện tại');

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $productIds = array_map('intval', $validated['product_ids']);
        $reason = trim($validated['reason']);
        $products = collect();

        DB::transaction(function () use ($user, $branchId, $productIds, $reason, &$products): void {
            $products = $this->branchProducts($user->restaurant_id, (int) $branchId)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get();

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'product_ids' => 'Một hoặc nhiều món không thuộc chi nhánh đang thao tác.',
                ]);
            }

            $until = now()->endOfDay();
            foreach ($products as $product) {
                $oldValues = [
                    'out_of_stock_until' => $product->out_of_stock_until?->toIso8601String(),
                    'out_of_stock_reason' => $product->out_of_stock_reason,
                ];

                $product->update([
                    'out_of_stock_until' => $until,
                    'out_of_stock_reason' => $reason,
                    'paused_until' => null,
                ]);

                AuditLog::log('kitchen_menu_unavailable', 'updated', $product, $oldValues, [
                    'out_of_stock_until' => $until->toIso8601String(),
                    'out_of_stock_reason' => $reason,
                    'branch_id' => (int) $branchId,
                ]);
            }
        });

        event(new ProductStockUpdated((int) $user->restaurant_id));

        $notification = new KitchenMenuUnavailableNotification(
            $products->pluck('name')->map(fn ($name): string => (string) $name)->all(),
            $branchName,
            $reason,
        );
        User::where('restaurant_id', $user->restaurant_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
            ->get()
            ->each(fn (User $owner) => $owner->notify($notification));

        return back()->with(
            'success',
            'Đã tạm ngưng '.count($productIds).' món tại '.$branchName.' đến hết ngày hôm nay.',
        );
    }

    private function branchProducts(int $restaurantId, int $branchId)
    {
        return Product::where('restaurant_id', $restaurantId)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where('is_active', true);
    }

    private function serializeProduct(Product $product): array
    {
        $isUnavailable = $product->out_of_stock_until?->isFuture() ?? false;
        $isPaused = $product->paused_until?->isFuture() ?? false;

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'price' => (float) $product->price,
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'category_name' => $product->category?->name ?? 'Món khác',
            'image_url' => $product->image_url,
            'is_available' => (bool) $product->is_available,
            'is_unavailable' => $isUnavailable,
            'is_paused' => $isPaused,
            'out_of_stock_until' => $product->out_of_stock_until?->toIso8601String(),
            'out_of_stock_reason' => $product->out_of_stock_reason,
        ];
    }
}
