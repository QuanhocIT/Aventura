<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    /** Trạng thái vận hành dẫn xuất — KHÔNG lưu trong DB, luôn tính lại theo giờ hiện tại. */
    public const STATUS_PAUSED = 'paused';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXHAUSTED = 'exhausted';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'budget_cap' => 'decimal:2',
        'budget_spent' => 'decimal:2',
        'auto_deactivate_on_budget' => 'boolean',
        'is_stackable' => 'boolean',
        'conditions' => 'array',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromotionUsage::class);
    }

    public function isBudgetExhausted(): bool
    {
        if ($this->budget_cap === null) {
            return false;
        }

        return (float) $this->budget_spent >= (float) $this->budget_cap;
    }

    public function remainingBudget(): ?float
    {
        if ($this->budget_cap === null) {
            return null;
        }

        return max(0, (float) $this->budget_cap - (float) $this->budget_spent);
    }

    public function hasStarted(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();

        return $this->start_date === null || $this->start_date->lessThanOrEqualTo($at);
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? now();

        return $this->end_date !== null && $this->end_date->lessThan($at);
    }

    /**
     * Số lượt đã dùng — đọc từ withCount('usages') nếu đã eager-load để tránh N+1.
     */
    public function usageCount(): int
    {
        return (int) ($this->usages_count ?? $this->usages()->count());
    }

    public function isUsageLimitReached(): bool
    {
        if ($this->usage_limit === null) {
            return false;
        }

        return $this->usageCount() >= $this->usage_limit;
    }

    /**
     * Trạng thái vận hành thực tế, thay cho việc chỉ đọc cờ is_active —
     * cờ đó chỉ được cron promotions:expire-outdated hạ mỗi ngày một lần, nên
     * một chương trình đã hết hạn vẫn hiện "Hoạt động" cho tới lần chạy kế tiếp.
     */
    public function operationalStatus(?\DateTimeInterface $at = null): string
    {
        if (! $this->is_approved) {
            return self::STATUS_PENDING;
        }

        if (! $this->is_active) {
            return self::STATUS_PAUSED;
        }

        if ($this->isExpired($at)) {
            return self::STATUS_EXPIRED;
        }

        if (! $this->hasStarted($at)) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->isBudgetExhausted() || $this->isUsageLimitReached()) {
            return self::STATUS_EXHAUSTED;
        }

        return self::STATUS_RUNNING;
    }

    /**
     * Chương trình có thực sự áp được vào đơn ngay lúc này hay không.
     */
    public function isRedeemable(?\DateTimeInterface $at = null): bool
    {
        return $this->operationalStatus($at) === self::STATUS_RUNNING;
    }
}
