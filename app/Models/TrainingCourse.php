<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingCourse extends Model
{
    use BelongsToRestaurant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'target_roles' => 'array',
            'target_branch_ids' => 'array',
            'required_for_new_hires' => 'boolean',
            'requires_manager_signoff' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(TrainingLesson::class, 'course_id')->orderBy('sort_order');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(TrainingQuiz::class, 'course_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'course_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasManyThrough(TrainingActivityLog::class, TrainingEnrollment::class, 'course_id', 'enrollment_id');
    }
}
