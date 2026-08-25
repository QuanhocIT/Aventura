<?php

namespace App\Models;

use App\Models\Concerns\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingLesson extends Model
{
    use HasBranch;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'requires_acknowledgement' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }
}
