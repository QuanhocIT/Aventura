<?php

namespace App\Jobs;

use App\Models\Ingredient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateAverageCostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $restaurantId,
        public int $ingredientId,
        public float $oldQty,
        public float $newQty,
        public float $newCost
    ) {}

    public function handle(): void
    {
        $ingredient = Ingredient::withoutGlobalScopes()->find($this->ingredientId);
        
        if (!$ingredient) {
            Log::warning("RecalculateAverageCostJob: Ingredient not found", ['ingredient_id' => $this->ingredientId]);
            return;
        }

        $oldAvg = (float) $ingredient->average_cost;

        // Calculate average cost: (old quantity * old average cost + new quantity * new cost) / total quantity
        $newAvg = ($this->oldQty + $this->newQty) > 0
            ? (($this->oldQty * $oldAvg) + ($this->newQty * $this->newCost)) / ($this->oldQty + $this->newQty)
            : $this->newCost;

        $ingredient->update(['average_cost' => round($newAvg, 2)]);

        // Recalculate product cost_price for all products that use this ingredient in their recipe
        $recipes = \App\Models\ProductRecipe::where('ingredient_id', $this->ingredientId)->get();
        foreach ($recipes as $recipe) {
            $product = \App\Models\Product::find($recipe->product_id);
            if ($product) {
                $totalCost = 0.0;
                $productRecipes = \App\Models\ProductRecipe::where('product_id', $product->id)
                    ->with('ingredient')
                    ->get();
                foreach ($productRecipes as $pr) {
                    $ingCost = $pr->ingredient ? (float) $pr->ingredient->average_cost : 0.0;
                    $prQty = (float) $pr->quantity;
                    $prWaste = (float) $pr->waste_rate;
                    $totalCost += $prQty * $ingCost * (1.0 + ($prWaste / 100.0));
                }
                $product->update(['cost_price' => round($totalCost, 2)]);
            }
        }

        Log::info("RecalculateAverageCostJob: Recalculated average cost and updated " . $recipes->count() . " products.", [
            'ingredient_id' => $this->ingredientId,
            'old_avg' => $oldAvg,
            'new_avg' => $newAvg,
        ]);
    }
}
