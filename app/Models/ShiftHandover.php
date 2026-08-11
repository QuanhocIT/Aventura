<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một phiên bàn giao ca.
 *
 * Ca ra lập phiếu (draft), điền checklist rồi nộp (pending_acceptance); ca vào
 * kiểm tra rồi nhận (accepted) hoặc báo không khớp (disputed).
 */
class ShiftHandover extends Model
{
    use BelongsToRestaurant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_acceptance';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISPUTED = 'disputed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'cash_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function fromShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'from_shift_id');
    }

    public function toShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'to_shift_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function shiftClosing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }

    public function cashHandover(): BelongsTo
    {
        return $this->belongsTo(CashHandover::class, 'cash_handover_id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(ShiftHandoverCheck::class, 'handover_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    /**
     * Còn mục bắt buộc nào chưa tick không.
     *
     * Mẫu có thể được sửa sau khi phiên bàn giao đã mở, nên phải đối chiếu với
     * danh sách mục hiện tại chứ không dựa vào số dòng check đã tạo.
     */
    public function unfinishedItems(): int
    {
        $totalItems = $this->template
            ? ChecklistItem::where('template_id', $this->template_id)->count()
            : 0;

        $done = $this->checks()->where('is_done', true)->count();

        return max(0, $totalItems - $done);
    }
}
