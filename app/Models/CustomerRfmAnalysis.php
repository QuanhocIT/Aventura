<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRfmAnalysis extends Model
{
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
