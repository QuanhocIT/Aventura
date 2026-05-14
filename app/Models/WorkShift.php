<?php

namespace App\Models;

use Database\Factories\Hr\WorkShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class, 'shift_id');
    }

    protected static function newFactory(): Factory
    {
        return WorkShiftFactory::new();
    }
}
