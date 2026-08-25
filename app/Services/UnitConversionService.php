<?php

namespace App\Services;

use App\Models\ProductRecipe;
use App\Models\Unit;

class UnitConversionService
{
    /**
     * Convert a recipe quantity into the ingredient's stock unit.
     *
     * A recipe may be entered as 100 g while the inventory is received as
     * kilograms. The conversion is also used by reservations and COGS so the
     * same physical quantity is used in every workflow.
     */
    public function recipeQuantityInIngredientUnit(ProductRecipe $recipe): float
    {
        $recipe->loadMissing(['unit', 'ingredient.unit']);

        return $this->convert(
            (float) $recipe->quantity,
            $recipe->unit,
            $recipe->ingredient?->unit,
        );
    }

    public function convert(float $quantity, ?Unit $from, ?Unit $to): float
    {
        if ($quantity == 0.0 || ! $from || ! $to || $from->id === $to->id) {
            return $quantity;
        }

        if ($from->type !== $to->type) {
            throw new \RuntimeException(
                "Không thể quy đổi đơn vị {$from->symbol} sang {$to->symbol}: khác loại đơn vị."
            );
        }

        return $quantity * ($this->factor($from) / $this->factor($to));
    }

    public function factor(Unit $unit): float
    {
        $configured = (float) ($unit->conversion_factor_to_base ?? 0);
        if ($configured > 0) {
            return $configured;
        }

        return match (strtolower(trim((string) $unit->symbol))) {
            'kg', 'kilogram', 'kilograms' => 1000.0,
            'g', 'gram', 'grams' => 1.0,
            'l', 'liter', 'liters', 'lít' => 1000.0,
            'ml', 'milliliter', 'milliliters' => 1.0,
            default => 1.0,
        };
    }
}
