<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalInspection extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(OperationalInspectionPlan::class, 'inspection_plan_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function leadInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_inspector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(OperationalInfringementReport::class, 'operational_inspection_id');
    }

    public function checklistCompletions(): HasMany
    {
        return $this->hasMany(ChecklistCompletion::class, 'operational_inspection_id');
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(OperationalCorrectiveAction::class, 'operational_inspection_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(OperationalEvidence::class, 'operational_inspection_id');
    }
}
