<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mục checklist trong phiên bàn giao ca.
 */
class ShiftHandoverCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function handover(): BelongsTo
    {
        return $this->belongsTo(ShiftHandover::class, 'handover_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'item_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
