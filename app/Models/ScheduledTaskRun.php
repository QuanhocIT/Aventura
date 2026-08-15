<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledTaskRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_name',
        'command',
        'started_at',
        'finished_at',
        'status',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];
}
