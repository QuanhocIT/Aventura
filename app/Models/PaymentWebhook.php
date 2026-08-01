<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
