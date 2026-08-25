<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần đếm tiền két.
 *
 * Sau khi số kỳ vọng đã được lộ (expected_revealed_at), bản ghi này khóa lại —
 * muốn đếm lại thì tạo bản ghi mới với sequence tăng dần, cả hai lần đều được
 * giữ để Chủ so sánh.
 */
class CashCount extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function (self $count): void {
            if ($count->getOriginal('expected_revealed_at') === null) {
                return;
            }

            // Chỉ cho phép gắn phiếu chốt ca vào sau; số đếm thì đóng băng.
            $allowed = ['shift_closing_id', 'updated_at'];

            if (array_diff(array_keys($count->getDirty()), $allowed) !== []) {
                throw new \RuntimeException(
                    'Phiếu đếm đã lộ số kỳ vọng nên không sửa được. Hãy tạo phiếu đếm lại.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'denominations' => 'array',
            'total_counted' => 'decimal:2',
            'expected_cash_at_reveal' => 'decimal:2',
            'expected_revealed_at' => 'datetime',
            'counted_at' => 'datetime',
        ];
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }

    public function shiftClosing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }

    public function isRevealed(): bool
    {
        return $this->expected_revealed_at !== null;
    }

    /**
     * Tổng tính lại từ các mệnh giá — dùng để đối chiếu với total_counted do
     * client gửi lên, tránh việc sửa tổng mà không sửa chi tiết.
     */
    public function denominationTotal(): float
    {
        $total = 0.0;

        foreach ($this->denominations ?? [] as $denomination => $quantity) {
            $total += (float) $denomination * (int) $quantity;
        }

        return round($total, 2);
    }
}
