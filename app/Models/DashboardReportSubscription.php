<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\CarbonInterface;

class DashboardReportSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Đến hạn gửi báo cáo định kỳ: chưa từng gửi, hoặc đã đủ một chu kỳ (tuần/tháng)
     * kể từ lần gửi gần nhất.
     */
    public function isDue(CarbonInterface $now): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->last_sent_at) {
            return true;
        }

        return $this->frequency === 'monthly'
            ? $this->last_sent_at->lt($now->copy()->subMonthNoOverflow())
            : $this->last_sent_at->lt($now->copy()->subWeek());
    }
}
