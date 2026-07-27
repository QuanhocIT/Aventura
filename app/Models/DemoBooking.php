<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemoBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'restaurant_name',
        'branch_count',
        'preferred_date',
        'preferred_time',
        'notes',
        'status',
        'contacted_at',
        'contacted_by',
        'admin_notes',
    ];

    protected $casts = [
        'branch_count'   => 'integer',
        'preferred_date' => 'date',
        'contacted_at'   => 'datetime',
    ];

    /**
     * Người dùng (Admin / Chuyên gia) đã liên hệ xử lý đơn đăng ký demo.
     */
    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by');
    }
}
