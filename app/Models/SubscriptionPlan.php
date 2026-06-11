<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Tenant\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    protected $guarded = [];

    public function shouldIncludeSystemShared(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_custom' => 'boolean',
            'max_dishes' => 'integer',
        ];
    }

    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'plan_id');
    }

    protected static function newFactory(): Factory
    {
        return SubscriptionPlanFactory::new();
    }
}
