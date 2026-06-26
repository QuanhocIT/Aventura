<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceMaintenanceStatus extends Model
{
    use HasFactory;

    protected $table = 'service_maintenance_statuses';

    protected $fillable = [
        'service_key',
        'name',
        'port',
        'is_maintenance',
        'maintenance_message',
        'last_checked_at',
        'last_status',
        'last_latency',
        'error_message',
    ];

    protected $casts = [
        'is_maintenance' => 'boolean',
        'port' => 'integer',
        'last_latency' => 'float',
        'last_checked_at' => 'datetime',
    ];
}
