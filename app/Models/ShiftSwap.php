<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwap extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    public function requesterAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class, 'requester_assignment_id');
    }

    public function receiverAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleAssignment::class, 'receiver_assignment_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
