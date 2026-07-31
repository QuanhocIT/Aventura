<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleRegistration extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            if ($registration->branch_id === null && $registration->employee_id) {
                $registration->branch_id = Employee::withoutGlobalScopes()
                    ->whereKey($registration->employee_id)
                    ->value('branch_id');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'shift_id');
    }
}
