<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemAlertRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'channels' => 'array',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SystemAlert::class);
    }
}
