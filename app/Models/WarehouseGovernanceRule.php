<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseGovernanceRule extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_auto_approve_variance_amount' => 'decimal:2',
            'max_auto_approve_variance_percent' => 'decimal:2',
            'require_seal_code_on_dispatch' => 'boolean',
            'auto_dispute_on_discrepancy' => 'boolean',
            'penalty_deduction_enabled' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
