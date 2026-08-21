<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierClaim extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'loss_amount' => 'decimal:2',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'evidence_paths' => 'array',
        ];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
