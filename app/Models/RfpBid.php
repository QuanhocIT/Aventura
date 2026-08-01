<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfpBid extends Model
{
    use HasFactory;

    protected $table = 'rfp_bids';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'proposed_delivery_date' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    public function rfp(): BelongsTo
    {
        return $this->belongsTo(RequestForProposal::class, 'rfp_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfpBidItem::class, 'rfp_bid_id');
    }
}
