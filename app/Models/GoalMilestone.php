<?php

namespace App\Models;

use App\Models\Concerns\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalMilestone extends Model
{
    use HasBranch;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reached' => 'boolean', 'notified' => 'boolean', 'reached_at' => 'datetime'];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(BusinessGoal::class, 'goal_id');
    }
}
