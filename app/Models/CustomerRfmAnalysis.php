<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRfmAnalysis extends Model
{
    use BelongsToRestaurant;
    protected $table = 'customer_rfm_analysis';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_calculated_at' => 'datetime',
            'monetary_amount' => 'float',
            'recency_score' => 'integer',
            'frequency_score' => 'integer',
            'monetary_score' => 'integer',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
