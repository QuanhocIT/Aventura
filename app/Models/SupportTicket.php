<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Models\Concerns\BelongsToRestaurant;

class SupportTicket extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['sla_status'];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function getSlaStatusAttribute(): string
    {
        if ($this->first_response_at) {
            if ($this->sla_due_at && $this->first_response_at->gt($this->sla_due_at)) {
                return 'breached';
            }
            return 'fulfilled';
        }

        if (!$this->sla_due_at) {
            return 'pending';
        }

        if (now()->gt($this->sla_due_at)) {
            return 'breached';
        }

        if (now()->addHour()->gt($this->sla_due_at)) {
            return 'warning';
        }

        return 'pending';
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }
}