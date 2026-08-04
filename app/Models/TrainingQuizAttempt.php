<?php

namespace App\Models;

use App\Models\Concerns\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingQuizAttempt extends Model
{
    use HasBranch;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'answers' => 'array'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'enrollment_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TrainingQuiz::class, 'quiz_id');
    }
}
