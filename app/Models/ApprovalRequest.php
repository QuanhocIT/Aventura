<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Support\ApprovalOperations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use BelongsToRestaurant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Vượt thẩm quyền Quản lý — chỉ Chủ doanh nghiệp còn xử lý được. */
    public const STATUS_ESCALATED = 'escalated';

    /** Thẩm quyền tối thiểu cần có để xử lý yêu cầu. */
    public const AUTHORITY_OWNER = 'owner';

    public const AUTHORITY_MANAGER = 'manager';

    protected $fillable = [
        'restaurant_id',
        'branch_id',
        'requester_id',
        'subject_employee_id',
        'reviewer_id',
        'decided_by_role',
        'required_authority',
        'policy_id',
        'operation_type',
        'operation_data',
        'amount_involved',
        'status',
        'rejection_reason',
        'reviewed_at',
        'escalated_at',
        'escalation_reason',
    ];

    protected $casts = [
        'operation_data' => 'array',
        'amount_involved' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function subjectEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'subject_employee_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'policy_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'approval_request_id');
    }

    /** Yêu cầu còn chờ xử lý, kể cả khi đã bị đẩy lên Chủ. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ESCALATED]);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    /**
     * Giới hạn theo các chi nhánh người dùng được phép thấy. Chủ và Super Admin
     * thấy toàn bộ; Quản lý chỉ thấy chi nhánh mình phụ trách.
     *
     * @param  list<int>  $branchIds
     */
    public function scopeForBranches(Builder $query, array $branchIds): Builder
    {
        return $query->whereIn('branch_id', $branchIds);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ESCALATED], true);
    }

    public function operationLabel(): string
    {
        return ApprovalOperations::label($this->operation_type);
    }
}
