<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biên bản bàn giao tiền giữa hai ca.
 *
 * Chỉ hoàn tất khi cả người giao lẫn người nhận đã ký — một chữ ký thôi thì
 * vẫn còn tranh cãi được về số tiền đã trao.
 */
class CashHandover extends Model
{
    use BelongsToRestaurant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISPUTED = 'disputed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'from_signed_at' => 'datetime',
            'to_signed_at' => 'datetime',
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

    public function shiftClosing(): BelongsTo
    {
        return $this->belongsTo(ShiftClosing::class, 'shift_closing_id');
    }

    public function isFullySigned(): bool
    {
        return $this->from_signed_at !== null && $this->to_signed_at !== null;
    }
}
