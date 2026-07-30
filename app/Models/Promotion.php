<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'budget_cap' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'auto_deactivate_on_budget' => 'boolean',
        'is_stackable' => 'boolean',
        'conditions' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isBudgetExhausted(): bool
    {
        if ($this->budget_cap === null) {
            return false;
        }

        return (float) $this->budget_spent >= (float) $this->budget_cap;
    }

    public function remainingBudget(): ?float
    {
        if ($this->budget_cap === null) {
            return null;
        }

        return max(0, (float) $this->budget_cap - (float) $this->budget_spent);
    }
}
