<?php

namespace App\Models\Delivery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipperLocationLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'speed_kmh' => 'float',
            'logged_at' => 'datetime',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }
}
