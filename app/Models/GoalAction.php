<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalAction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(BusinessGoal::class, 'goal_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
