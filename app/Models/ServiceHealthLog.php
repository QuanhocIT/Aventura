<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceHealthLog extends Model
{
    use HasFactory;

    protected $table = 'service_health_logs';

    // Disable default timestamps since we only use created_at
    public $timestamps = false;

    protected $fillable = [
        'service_key',
        'status',
        'latency',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'latency' => 'float',
        'created_at' => 'datetime',
    ];
}
