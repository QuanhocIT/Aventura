<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessGoal extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
        ];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class, 'goal_id')->orderBy('threshold_percent');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GoalAction::class, 'goal_id');
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast() && $this->status === 'active';
    }
}
