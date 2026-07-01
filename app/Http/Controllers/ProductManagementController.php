<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

        $categories = Cache::remember("restaurant_{$restaurantId}_categories", 3600, function () use ($restaurantId) {
            return ProductCategory::where('restaurant_id', $restaurantId)
                ->orderBy('display_order')
                ->get();
        });

        $products = Cache::remember("restaurant_{$restaurantId}_products", 3600, function () use ($restaurantId) {
            return Product::where('restaurant_id', $restaurantId)
                ->with('category')
                ->latest()
                ->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'code'         => $p->code,
                    'name'         => $p->name,
                    'price'        => $p->price,
                    'description'  => $p->description,
                    'is_available' => (bool) $p->is_available,
                    'category'     => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'description' => $p->category->description] : null,
                ])->toArray();
        });

        return Inertia::render('products/Index', [
            'categories' => $categories,
            'products'   => $products,
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
        ]);

        ProductCategory::create([
            'restaurant_id' => $user->restaurant_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'description' => $data['description'] ?? null,
            'display_order' => ProductCategory::where('restaurant_id', $user->restaurant_id)->count() + 1,
            'status' => 'active',
        ]);

        Cache::forget("restaurant_{$user->restaurant_id}_categories");
        Cache::forget("restaurant_{$user->restaurant_id}_products");

        return back()->with('success', 'Đã thêm nhóm món ăn mới.');
    }

    /**
     * Thêm món ăn mới (Product).
     */
    public function storeProduct(StoreProductRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        }

        Product::create([
            'restaurant_id' => $user->restaurant_id,
            'category_id' => $data['category_id'],
            'code' => 'PROD-' . Str::upper(Str::random(6)),
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(4)),
            'price' => $data['price'],
            'description' => $data['description'] ?? null,
            'image_url' => $imageUrl,
            'is_active' => true,
            'is_available' => true,
            'track_inventory' => true,
        ]);

        Cache::forget("restaurant_{$user->restaurant_id}_products");

        return back()->with('success', 'Đã thêm món ăn mới vào thực đơn.');
    }

    /**
     * Cập nhật thông tin sản phẩm.
     */
    public function updateProduct(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image_url && str_starts_with($product->image_url, '/storage/')) {
                \Storage::disk('public')->delete(str_replace('/storage/', '', $product->image_url));
            }
            $path = $request->file('image')->store('products', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $product->update($data);

        Cache::forget("restaurant_{$user->restaurant_id}_products");

        return back()->with('success', 'Đã cập nhật thông tin món ăn.');
    }

    /**
     * Xóa sản phẩm.
     */
    public function destroyProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        abort_if($product->restaurant_id !== $user->restaurant_id, 403);

        $product->delete();

        Cache::forget("restaurant_{$user->restaurant_id}_products");

        return back()->with('success', 'Đã xóa món ăn khỏi thực đơn.');
    }

    /**
     * Xóa nhóm món ăn.
     */
    public function destroyCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $user = $request->user();
        abort_if($category->restaurant_id !== $user->restaurant_id, 403);

        $category->delete();

        Cache::forget("restaurant_{$user->restaurant_id}_categories");
        Cache::forget("restaurant_{$user->restaurant_id}_products");

        return back()->with('success', 'Đã xóa nhóm món ăn.');
    }
}
