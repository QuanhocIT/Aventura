<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMaintenanceSchedule extends Model
{
    protected $fillable = [
        'title',
        'downtime_start',
        'downtime_end',
        'services',
        'banner_message',
        'status',
        'notified_24h',
        'notified_1h',
    ];

    protected function casts(): array
    {
        return [
            'downtime_start' => 'datetime',
            'downtime_end' => 'datetime',
            'services' => 'array',
            'notified_24h' => 'boolean',
            'notified_1h' => 'boolean',
        ];
    }
}
