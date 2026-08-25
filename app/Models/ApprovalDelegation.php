<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'delegator_id',
        'delegatee_id',
        'module',
        'max_amount_limit',
        'start_date',
        'end_date',
        'is_active',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'max_amount_limit' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegatee_id');
    }

    public function isValidForNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = today();

        return $today->betweenInclusive($this->start_date, $this->end_date);
    }
}
