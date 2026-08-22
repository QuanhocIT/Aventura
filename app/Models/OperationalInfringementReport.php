<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalInfringementReport extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'penalty_amount' => 'decimal:2',
            'infringement_date' => 'date',
            'remediation_deadline' => 'date',
            'remediation_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reinspected_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function inspectionPlan(): BelongsTo
    {
        return $this->belongsTo(OperationalInspectionPlan::class, 'inspection_plan_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompanyPolicy::class, 'policy_id');
    }

    public function offender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offender_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reinspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reinspected_by');
    }
}
