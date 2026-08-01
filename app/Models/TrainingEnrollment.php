<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingEnrollment extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'completed_lessons' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(TrainingQuizAttempt::class, 'enrollment_id');
    }

    public function recalculateProgress(): void
    {
        $totalLessons = $this->course->lessons()->count();
        $completedCount = count($this->completed_lessons ?? []);
        $percent = $totalLessons > 0 ? (int) round(($completedCount / $totalLessons) * 100) : 0;

        $this->update(['progress_percent' => min(100, $percent)]);
    }
}
