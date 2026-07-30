<?php

namespace App\Models;

use App\Services\SentimentAnalysisService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformFeedback extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'platform_feedbacks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sentiment_score' => 'float',
        ];
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id')->withTrashed();
    }
}
