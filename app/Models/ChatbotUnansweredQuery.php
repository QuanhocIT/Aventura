<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotUnansweredQuery extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'query',
        'query_hash',
        'best_score',
        'session_id',
        'user_id',
        'restaurant_id',
        'source',
        'occurrence_count',
        'last_seen_at',
        'is_resolved',
    ];

    protected $casts = [
        'best_score'       => 'float',
        'occurrence_count' => 'integer',
        'is_resolved'      => 'boolean',
        'last_seen_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
