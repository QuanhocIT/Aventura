<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuPriceTest extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'test_price' => 'decimal:2',
            'revenue_original' => 'decimal:2',
            'revenue_test' => 'decimal:2',
            'results_json' => 'array',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running' && $this->start_at <= now() && ($this->end_at === null || $this->end_at > now());
    }

    public function getImpactPercent(): ?float
    {
        if ($this->orders_original === 0 || $this->orders_test === 0) {
            return null;
        }

        $avgOriginal = $this->revenue_original / $this->orders_original;
        $avgTest = $this->revenue_test / $this->orders_test;

        return round((($avgTest - $avgOriginal) / $avgOriginal) * 100, 1);
    }
}
