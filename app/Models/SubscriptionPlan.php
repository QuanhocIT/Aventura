<?php

namespace App\Models;

use Database\Factories\Tenant\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
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
