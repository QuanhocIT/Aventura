<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RestaurantBranch;
use App\Models\SystemSetting;
use App\Support\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductManagementController extends Controller
{
    /**
     * Trang Thực đơn & Món.
     */
    public function productsPage(Request $request): Response
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;
        $context = app(TenantContext::class);
        $scopeKey = $context->scopeKey();

        $categories = Cache::remember("restaurant_{$restaurantId}_categories:scope:{$scopeKey}", 3600, function () use ($restaurantId, $context) {
            return ProductCategory::where('restaurant_id', $restaurantId)
                ->when($context->isBranchScoped(), fn ($q) => $q->where(function ($q) use ($context) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $context->activeBranchId());
                }))
                ->orderBy('display_order')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description,
                    'branch_id' => $c->branch_id,
                    'branch_name' => $c->branch?->name,
                ])
                ->toArray();
        });

        $products = Cache::remember("restaurant_{$restaurantId}_products:scope:{$scopeKey}", 3600, function () use ($restaurantId, $context) {
            return Product::where('restaurant_id', $restaurantId)
                ->when($context->isBranchScoped(), fn ($q) => $q->where(function ($q) use ($context) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $context->activeBranchId());
                }))
                ->with(['category', 'recipes'])
                ->latest()
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'price' => $p->price,
                    'earn_points' => (int) ($p->earn_points ?? 0),
                    'redeem_points' => (int) ($p->redeem_points ?? 0),
                    'description' => $p->description,
                    'is_available' => (bool) $p->is_available,
                    'is_active' => (bool) $p->is_active,
                    'is_processed' => (bool) ($p->is_processed ?? true),
                    'has_recipes' => $p->recipes->isNotEmpty(),
                    'recipes_count' => $p->recipes->count(),
                    'branch_id' => $p->branch_id,
                    'branch_name' => $p->branch?->name,
                    'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'description' => $p->category->description] : null,
                ])->toArray();
        });

        return Inertia::render('products/Index', [
            'categories' => $categories,
            'products' => $products,
            'branches' => $user->isOwner()
                ? $user->restaurant->branches()->where('status', 'active')->get(['id', 'name'])
                : $user->restaurant->branches()->whereKey($context->activeBranchId())->get(['id', 'name']),
            'activeBranchId' => $context->activeBranchId(),
            'branchScope' => $context->scope(),
            'canCreateShared' => $user->isOwner(),
        ]);
    }

    /**
     * Thêm nhóm món ăn (ProductCategory).
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'scope' => ['required', 'in:shared,branch'],
            'branch_id' => ['nullable', 'integer', Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id)],
        ]);
        $branchId = $this->resolveCatalogBranch($user, app(TenantContext::class), $data);

        ProductCategory::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'display_order' => ProductCategory::where('restaurant_id', $user->restaurant_id)->where('branch_id', $branchId)->count() + 1,
            'status' => 'active',
        ]);

        $this->forgetCatalogCaches($user->restaurant_id, app(TenantContext::class), $branchId);

        return back()->with('success', 'Đã thêm nhóm món ăn mới.');
    }

    /**
     * Thêm món ăn mới (Product).
     */
    public function storeProduct(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['owner', 'manager']), 403);

        $maxSize = SystemSetting::get('upload_menu_image_max', 2048);
        $data = $request->validate([
            'category_id' => ['required', "exists:product_categories,id,restaurant_id,{$user->restaurant_id}"],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_processed' => ['nullable', 'boolean'],
            'description' => ['required', 'string', 'min:5'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxSize],
            'scope' => ['required', 'in:shared,branch'],
            'branch_id' => ['nullable', 'integer', Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $user->restaurant_id)],
        ]);
        $context = app(TenantContext::class);
        $branchId = $this->resolveCatalogBranch($user, $context, $data);
        $category = ProductCategory::where('restaurant_id', $user->restaurant_id)->findOrFail($data['category_id']);
        if ($category->branch_id !== null && (int) $category->branch_id !== $branchId) {
            throw ValidationException::withMessages(['category_id' => 'Nhóm món phải thuộc cùng chi nhánh với món ăn.']);
        }
        if ($branchId === null && $category->branch_id !== null) {
            throw ValidationException::withMessages(['category_id' => 'Món dùng chung toàn chuỗi chỉ được gắn vào nhóm dùng chung.']);
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/'.$path;
        }

        Product::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $branchId,
            'category_id' => $data['category_id'],
            'code' => 'PROD-'.Str::upper(Str::random(6)),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'price' => $data['price'],
            'earn_points' => $data['earn_points'] ?? 0,
            'redeem_points' => $data['redeem_points'] ?? 0,
            'description' => $data['description'] ?? null,
            'image_url' => $imageUrl,
            'is_active' => true,
            'is_available' => true,
            'is_processed' => (bool) ($data['is_processed'] ?? true),
            'track_inventory' => true,
        ]);

        $this->forgetCatalogCaches($user->restaurant_id, $context, $branchId);

        return back()->with('success', 'Đã thêm món ăn mới vào thực đơn.');
    }

    /**
     * Cập nhật thông tin sản phẩm.
     */
    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($product->branch_id), 403);

        $maxSize = SystemSetting::get('upload_menu_image_max', 2048);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'earn_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'redeem_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'category_id' => ['sometimes', 'nullable', "exists:product_categories,id,restaurant_id,{$user->restaurant_id}"],
            'description' => ['sometimes', 'required', 'string', 'min:5'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_processed' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxSize],
        ]);

        if ($request->hasFile('image')) {
            if ($product->image_url && str_starts_with($product->image_url, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
            }
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = '/storage/'.$path;
        }

        $product->update($data);

        $this->forgetCatalogCaches($user->restaurant_id, app(TenantContext::class), $product->branch_id);

        return back()->with('success', 'Đã cập nhật thông tin món ăn.');
    }

    /**
     * Xóa sản phẩm.
     */
    public function destroyProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($product->branch_id), 403);

        $product->delete();

        $this->forgetCatalogCaches($user->restaurant_id, app(TenantContext::class), $product->branch_id);

        return back()->with('success', 'Đã xóa món ăn khỏi thực đơn.');
    }

    /**
     * Xóa nhóm món ăn.
     */
    public function destroyCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_if($category->restaurant_id !== $user->restaurant_id, 403);
        abort_unless($user->canAccessBranch($category->branch_id), 403);

        $category->delete();

        $this->forgetCatalogCaches($user->restaurant_id, app(TenantContext::class), $category->branch_id);

        return back()->with('success', 'Đã xóa nhóm món ăn.');
    }

    private function resolveCatalogBranch($user, TenantContext $context, array $data): ?int
    {
        if (($data['scope'] ?? 'branch') === 'shared') {
            if (! $user->isOwner()) {
                throw ValidationException::withMessages(['scope' => 'Chỉ chủ doanh nghiệp được tạo dữ liệu dùng chung toàn chuỗi.']);
            }

            return null;
        }

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if (! $branchId || ! $user->canAccessBranch($branchId)) {
            throw ValidationException::withMessages(['branch_id' => 'Vui lòng chọn một chi nhánh mà bạn có quyền quản lý.']);
        }

        if ($context->isBranchScoped() && $branchId !== $context->activeBranchId()) {
            throw ValidationException::withMessages(['branch_id' => 'Chi nhánh thao tác phải trùng chi nhánh hiện tại.']);
        }

        return $branchId;
    }

    private function forgetCatalogCaches(int $restaurantId, TenantContext $context, ?int $branchId): void
    {
        foreach (['all', 'none'] as $scope) {
            Cache::forget("restaurant_{$restaurantId}_products:scope:{$scope}");
            Cache::forget("restaurant_{$restaurantId}_categories:scope:{$scope}");
        }

        if ($branchId) {
            $scopeKey = TenantContext::branchScopeKey($branchId);
            Cache::forget("restaurant_{$restaurantId}_products:scope:{$scopeKey}");
            Cache::forget("restaurant_{$restaurantId}_categories:scope:{$scopeKey}");
        } else {
            RestaurantBranch::where('restaurant_id', $restaurantId)
                ->pluck('id')
                ->each(function ($id) use ($restaurantId): void {
                    $scopeKey = TenantContext::branchScopeKey((int) $id);
                    Cache::forget("restaurant_{$restaurantId}_products:scope:{$scopeKey}");
                    Cache::forget("restaurant_{$restaurantId}_categories:scope:{$scopeKey}");
                });
        }

        Cache::forget("restaurant_{$restaurantId}_products");
        Cache::forget("restaurant_{$restaurantId}_categories");
    }
}
