<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use App\Services\SentimentAnalysisService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerFeedback extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'customer_feedback';

    protected $guarded = [];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'rating' => 'integer',
        'items_rating' => 'array',
        'staff_rating' => 'array',
        'sentiment_score' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $feedback) {
            if (! $feedback->isDirty('content') && $feedback->exists) {
                return;
            }

            $result = app(SentimentAnalysisService::class)->analyze($feedback->content);
            $feedback->sentiment = $result['sentiment'];
            $feedback->sentiment_score = $result['score'];
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
