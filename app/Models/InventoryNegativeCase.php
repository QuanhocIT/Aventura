<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryNegativeCase extends \Illuminate\Database\Eloquent\Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'negative_quantity' => 'float',
            'estimated_value' => 'float',
            'detected_quantity' => 'float',
            'detected_value' => 'float',
            'owner_approval_required' => 'boolean',
            'due_at' => 'datetime',
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'verification_requested_at' => 'datetime',
            'verified_quantity' => 'float',
            'verified_at' => 'datetime',
            'reopened_at' => 'datetime',
            'expected_restock_at' => 'date',
            'approved_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verification_requested_by');
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'source_transaction_id');
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryNegativeCaseEvent::class, 'negative_case_id');
    }
}
