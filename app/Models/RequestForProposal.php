<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestForProposal extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'request_for_proposals';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfpItem::class, 'rfp_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(RfpBid::class, 'rfp_id');
    }
}
