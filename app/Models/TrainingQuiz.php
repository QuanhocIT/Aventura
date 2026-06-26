<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuiz extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['questions' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }
}
