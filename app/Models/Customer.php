<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Database\Factories\Restaurant\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'is_b2b' => 'boolean',
            'credit_limit' => 'decimal:2',
            'current_debt' => 'decimal:2',
            'date_of_birth' => 'date',
            'last_order_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CustomerFactory::new();
    }

    public function rfmAnalysis()
    {
        return $this->hasOne(CustomerRfmAnalysis::class);
    }

    public function behaviorLogs()
    {
        return $this->hasMany(CustomerBehaviorLog::class);
    }

    public function accountReceivables()
    {
        return $this->hasMany(AccountReceivable::class);
    }

    public function loyaltyTier()
    {
        return $this->belongsTo(LoyaltyTier::class, 'loyalty_tier_id');
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
