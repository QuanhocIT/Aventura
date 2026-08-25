<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurantOnly;
use App\Support\ApprovalOperations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sổ phê duyệt — bản ghi chỉ ghi thêm.
 *
 * Model chặn mọi thao tác sửa và xóa ở tầng ứng dụng. Đây là bằng chứng để Chủ
 * doanh nghiệp hậu kiểm quyết định của Quản lý, nên nếu sửa được thì mất ý nghĩa.
 */
class ApprovalDecision extends Model
{
    use BelongsToRestaurantOnly;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $decision): void {
            // Hậu kiểm của Chủ là ngoại lệ duy nhất: chỉ được đánh dấu đã xem,
            // không được chạm vào nội dung quyết định.
            $allowed = ['owner_reviewed_at', 'owner_reviewed_by'];

            if (array_diff(array_keys($decision->getDirty()), $allowed) !== []) {
                throw new \RuntimeException('Sổ phê duyệt không cho phép sửa nội dung quyết định.');
            }
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Sổ phê duyệt không cho phép xóa bản ghi.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount_involved' => 'decimal:2',
            'policy_snapshot' => 'array',
            'created_at' => 'datetime',
            'owner_reviewed_at' => 'datetime',
        ];
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function ownerReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_reviewed_by');
    }

    public function operationLabel(): string
    {
        return ApprovalOperations::label($this->operation_type);
    }

    /** Quyết định do Quản lý đưa ra — phần Chủ cần hậu kiểm. */
    public function scopeDelegated(Builder $query): Builder
    {
        return $query->where('authority_basis', 'policy_delegated');
    }

    public function scopeAwaitingOwnerReview(Builder $query): Builder
    {
        return $query->delegated()->whereNull('owner_reviewed_at');
    }
}
