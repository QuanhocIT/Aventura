<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function subRecipes(): HasMany
    {
        return $this->hasMany(SubRecipe::class, 'parent_ingredient_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function supplierOptions(): HasMany
    {
        return $this->hasMany(IngredientSupplier::class);
    }

    public function activeBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class)->where('status', 'active')->where('quantity_remaining', '>', 0)->orderBy('expiry_date', 'asc');
    }

    protected function casts(): array
    {
        return [
            'is_semi_finished' => 'boolean',
            'allowed_waste_ratio' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'default_shelf_life_days' => 'integer',
            'expiry_warning_days' => 'integer',
            'auto_waste_end_of_day' => 'boolean',
            'batch_tracking_required' => 'boolean',
            'storage_temperature_min_c' => 'decimal:2',
            'storage_temperature_max_c' => 'decimal:2',
            'lead_time_days' => 'integer',
            'safety_stock_quantity' => 'decimal:3',
        ];
    }

    public function getStorageTypeLabelAttribute(): string
    {
        return match ($this->storage_type) {
            'fresh' => '🥬 Hàng tươi sống',
            'daily' => '🥖 Bán trong ngày',
            'canned_packaged' => '🥫 Đồ đóng hộp / Đóng gói',
            'short_shelf' => '🥛 Hạn ngắn (5-15 ngày)',
            default => '🌾 Đồ khô & Gia vị',
        };
    }

    protected static function newFactory(): Factory
    {
        return IngredientFactory::new();
    }
}
