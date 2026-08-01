<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfpBidItem extends Model
{
    use HasFactory;

    protected $table = 'rfp_bid_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'proposed_price_per_unit' => 'decimal:2',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(RfpBid::class, 'rfp_bid_id');
    }

    public function rfpItem(): BelongsTo
    {
        return $this->belongsTo(RfpItem::class, 'rfp_item_id');
    }
}
