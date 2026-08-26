<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'assigned_at' => 'datetime',
            'assignment_accepted_at' => 'datetime',
            'assignment_rejected_at' => 'datetime',
            'work_started_at' => 'datetime',
            'branch_acknowledged_at' => 'datetime',
            'last_reopened_at' => 'datetime',
            'reopen_count' => 'integer',
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

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(OperationalInspection::class, 'operational_inspection_id');
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

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(OperationalCorrectiveAction::class, 'operational_report_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(OperationalEvidence::class, 'operational_report_id');
    }

    public function caseLinks(): HasMany
    {
        return $this->hasMany(OperationalCaseLink::class, 'operational_report_id');
    }
}
