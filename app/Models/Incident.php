<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sự cố khẩn cấp tại chi nhánh. Sự cố nghiêm trọng tự động báo Chủ. Bản ghi KHÔNG
 * được xoá (bằng chứng pháp lý) — mọi thay đổi đều để lại dấu vết qua status/report.
 */
class Incident extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
        'escalated_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'escalated' => 'boolean',
        'needs_shift_cover' => 'boolean',
        'injured_count' => 'integer',
    ];

    /** Loại sự cố LUÔN được coi là khẩn cấp → tự động báo Chủ. */
    public const CRITICAL_TYPES = ['fire', 'food_poisoning', 'accident'];

    protected static function booted(): void
    {
        // Cấm xoá tuyệt đối — sự cố là bằng chứng, chỉ được đóng bằng báo cáo.
        static::deleting(function () {
            throw new \RuntimeException('Không được phép xoá bản ghi sự cố. Sự cố chỉ có thể đóng bằng báo cáo xử lý.');
        });
    }

    /** Có phải sự cố cần báo Chủ ngay không (theo loại/mức độ/số người bị thương). */
    public function shouldAutoEscalate(): bool
    {
        return in_array($this->type, self::CRITICAL_TYPES, true)
            || in_array($this->severity, ['high', 'critical'], true)
            || (int) $this->injured_count > 0;
    }

    /** Thời hạn phản hồi đầu tiên theo mức độ rủi ro của sự cố. */
    public function responseSlaMinutes(): int
    {
        return match ($this->severity) {
            'critical' => 15,
            'high' => 30,
            'medium' => 120,
            default => 480,
        };
    }

    public function responseDueAt(): ?Carbon
    {
        return $this->occurred_at?->copy()->addMinutes($this->responseSlaMinutes());
    }

    public function responseTimeMinutes(): ?int
    {
        if (! $this->acknowledged_at || ! $this->occurred_at) {
            return null;
        }

        return (int) $this->occurred_at->diffInMinutes($this->acknowledged_at);
    }

    public function resolutionTimeMinutes(): ?int
    {
        if (! $this->resolved_at || ! $this->occurred_at) {
            return null;
        }

        return (int) $this->occurred_at->diffInMinutes($this->resolved_at);
    }

    public function isResponseOverdue(?Carbon $now = null): bool
    {
        if ($this->acknowledged_at) {
            return ($this->responseTimeMinutes() ?? 0) > $this->responseSlaMinutes();
        }

        return (bool) ($this->responseDueAt()?->isBefore($now ?? now()));
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
