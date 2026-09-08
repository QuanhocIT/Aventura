<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDiscrepancyDispute extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dispatched_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'discrepancy_quantity' => 'decimal:3',
            'financial_loss_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'waived_amount' => 'decimal:2',
            'claim_collected_amount' => 'decimal:2',
            'claim_collected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'write_off_at' => 'datetime',
        ];
    }

    public function claimCollector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_collected_by');
    }

    public function writeOffTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'write_off_transaction_id');
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }

    public function supplyRequestItem(): BelongsTo
    {
        return $this->belongsTo(SupplyRequestItem::class, 'supply_request_item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
