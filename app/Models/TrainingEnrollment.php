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
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_overdue' => 'boolean',
            'mandatory' => 'boolean',
            'awaiting_manager_approval' => 'boolean',
            'manager_approved_at' => 'datetime',
            'certificate_issued_at' => 'datetime',
            'certificate_expires_at' => 'datetime',
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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TrainingActivityLog::class, 'enrollment_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function recalculateProgress(): void
    {
        $course = $this->relationLoaded('course') ? $this->course : $this->course()->with(['lessons', 'quizzes'])->first();
        $lessons = $course?->lessons ?? collect();
        $requiredLessons = $lessons->where('is_required', true);
        $requiredLessons = $requiredLessons->isEmpty() ? $lessons : $requiredLessons;
        $completedLessons = collect($this->completed_lessons ?? [])->map(fn ($id) => (int) $id);
        $lessonCompleted = $requiredLessons->filter(fn (TrainingLesson $lesson): bool => $completedLessons->contains((int) $lesson->id))->count();

        $requiredQuizzes = $course?->quizzes?->where('is_required', true) ?? collect();
        $passedQuizzes = $requiredQuizzes->filter(fn (TrainingQuiz $quiz): bool => $this->quizAttempts()->where('quiz_id', $quiz->id)->where('passed', true)->exists())->count();
        $totalUnits = $requiredLessons->count() + $requiredQuizzes->count();
        $completedUnits = $lessonCompleted + $passedQuizzes;
        $percent = $totalUnits > 0 ? (int) round(($completedUnits / $totalUnits) * 100) : 0;

        $this->update(['progress_percent' => min(100, $percent)]);
    }
}
