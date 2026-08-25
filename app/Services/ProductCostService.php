<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductRecipe;
use Illuminate\Support\Collection;

/**
 * Tính giá vốn (cost_price) của món ăn từ công thức định lượng (BOM):
 *
 *     cost_price = Σ (định lượng × giá vốn TB nguyên liệu × (1 + hao hụt%))
 *
 * Trước đây phép tính này chỉ nằm trong RecalculateAverageCostJob, tức là
 * cost_price CHỈ được cập nhật khi nhập hàng làm đổi giá vốn nguyên liệu.
 * Sửa/xoá công thức không kích hoạt gì cả → giá vốn treo ở giá trị cũ và toàn
 * bộ phân tích biên lợi nhuận (Menu Engineering, BCG, cảnh báo margin thấp)
 * chạy trên số liệu sai.
 */
class ProductCostService
{
    public function __construct(private UnitConversionService $unitConversion) {}

    /**
     * Tính lại cost_price cho danh sách món. Trả về số món thực sự đổi giá vốn.
     *
     * @param  array<int>  $productIds
     */
    public function recalculateForProducts(array $productIds): int
    {
        $productIds = array_values(array_unique(array_filter($productIds)));

        if (empty($productIds)) {
            return 0;
        }

        $products = Product::withoutGlobalScopes()->whereIn('id', $productIds)->get();

        $recipesByProduct = ProductRecipe::withoutGlobalScopes()
            ->whereIn('product_id', $productIds)
            ->with(['ingredient:id,average_cost,unit_id', 'ingredient.unit:id,type,symbol,conversion_factor_to_base', 'unit:id,type,symbol,conversion_factor_to_base'])
            ->get()
            ->groupBy('product_id');

        $changed = 0;

        foreach ($products as $product) {
            $newCost = $this->costFromRecipes($recipesByProduct->get($product->id));

            // Món không có công thức: giữ nguyên cost_price nhập tay, không ghi đè về 0.
            if ($newCost === null) {
                continue;
            }

            if (round((float) $product->cost_price, 2) !== $newCost) {
                $product->update(['cost_price' => $newCost]);
                $changed++;
            }
        }

        return $changed;
    }

    /** Tính lại cho mọi món có dùng một nguyên liệu cụ thể (khi giá nguyên liệu đổi). */
    public function recalculateForIngredient(int $ingredientId): int
    {
        $productIds = ProductRecipe::withoutGlobalScopes()
            ->where('ingredient_id', $ingredientId)
            ->pluck('product_id')
            ->all();

        return $this->recalculateForProducts($productIds);
    }

    /** @param  Collection<int, ProductRecipe>|null  $recipes */
    private function costFromRecipes($recipes): ?float
    {
        if ($recipes === null || $recipes->isEmpty()) {
            return null;
        }

        $total = 0.0;

        foreach ($recipes as $recipe) {
            $ingredientCost = (float) ($recipe->ingredient?->average_cost ?? 0);
            $quantity = $this->unitConversion->recipeQuantityInIngredientUnit($recipe);
            $wasteRate = (float) $recipe->waste_rate;

            $total += $quantity * $ingredientCost * (1.0 + ($wasteRate / 100.0));
        }

        return round($total, 2);
    }
}
