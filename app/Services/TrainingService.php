<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\TrainingActivityLog;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingQuiz;
use App\Models\TrainingQuizAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TrainingService
{
    /**
     * Giao một khóa học cho nhân viên. Đây là nghiệp vụ trung tâm dùng chung
     * cho giao thủ công, giao theo vai trò và tự động onboarding.
     */
    public function assign(
        TrainingCourse $course,
        array $employeeIds,
        ?User $actor = null,
        ?Carbon $dueAt = null,
        ?bool $mandatory = null,
        ?string $reason = null,
    ): Collection {
        $employees = Employee::query()
            ->where('restaurant_id', $course->restaurant_id)
            ->whereIn('id', array_values(array_unique(array_map('intval', $employeeIds))))
            ->with('user.roles')
            ->get();

        if ($employees->count() !== count(array_unique(array_map('intval', $employeeIds)))) {
            throw new InvalidArgumentException('Danh sách nhân viên có người không thuộc nhà hàng này.');
        }

        $dueAt ??= now()->addDays(max(1, (int) ($course->due_days ?? 14)));
        $isMandatory = $mandatory ?? (bool) ($course->is_required || $course->required_for_new_hires);

        return DB::transaction(function () use ($course, $employees, $actor, $dueAt, $isMandatory, $reason): Collection {
            return $employees->map(function (Employee $employee) use ($course, $actor, $dueAt, $isMandatory, $reason): TrainingEnrollment {
                $enrollment = TrainingEnrollment::query()
                    ->where('restaurant_id', $course->restaurant_id)
                    ->where('course_id', $course->id)
                    ->where('employee_id', $employee->id)
                    ->lockForUpdate()
                    ->first();

                if (! $enrollment) {
                    $enrollment = TrainingEnrollment::create([
                        'restaurant_id' => $course->restaurant_id,
                        'branch_id' => $employee->branch_id,
                        'course_id' => $course->id,
                        'employee_id' => $employee->id,
                        'assigned_by' => $actor?->id,
                        'assigned_at' => now(),
                        'due_at' => $dueAt,
                        'mandatory' => $isMandatory,
                        'assignment_reason' => $reason,
                        'status' => 'enrolled',
                        'last_activity_at' => now(),
                    ]);
                    $this->log($enrollment, 'assigned', $actor, [
                        'reason' => $reason,
                        'mandatory' => $isMandatory,
                        'due_at' => $dueAt->toIso8601String(),
                    ]);

                    return $enrollment;
                }

                if ($enrollment->status === 'completed' && ! $this->certificateExpired($enrollment)) {
                    return $enrollment;
                }

                $resetProgress = in_array($enrollment->status, ['failed', 'completed'], true);
                if ($resetProgress) {
                    $enrollment->quizAttempts()->delete();
                }

                $enrollment->update([
                    'assigned_by' => $actor?->id ?? $enrollment->assigned_by,
                    'assigned_at' => $enrollment->assigned_at ?? now(),
                    'due_at' => $dueAt,
                    'mandatory' => $isMandatory,
                    'assignment_reason' => $reason ?: $enrollment->assignment_reason,
                    'status' => $resetProgress ? 'enrolled' : $enrollment->status,
                    'progress_percent' => $resetProgress ? 0 : $enrollment->progress_percent,
                    'completed_lessons' => $resetProgress ? null : $enrollment->completed_lessons,
                    'started_at' => $resetProgress ? null : $enrollment->started_at,
                    'completed_at' => $resetProgress ? null : $enrollment->completed_at,
                    'certificate_code' => $resetProgress ? null : $enrollment->certificate_code,
                    'certificate_issued_at' => $resetProgress ? null : $enrollment->certificate_issued_at,
                    'certificate_expires_at' => $resetProgress ? null : $enrollment->certificate_expires_at,
                    'manager_approved_by' => $resetProgress ? null : $enrollment->manager_approved_by,
                    'manager_approved_at' => $resetProgress ? null : $enrollment->manager_approved_at,
                    'last_score' => $resetProgress ? null : $enrollment->last_score,
                    'is_overdue' => false,
                    'awaiting_manager_approval' => false,
                ]);
                $this->log($enrollment, 'reassigned', $actor, ['reason' => $reason]);

                return $enrollment->fresh();
            });
        });
    }

    public function autoAssignRequiredCourses(Employee $employee): int
    {
        $courses = TrainingCourse::query()
            ->where('restaurant_id', $employee->restaurant_id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('is_required', true)->orWhere('required_for_new_hires', true);
            })
            ->with('enrollments')
            ->get()
            ->filter(fn (TrainingCourse $course): bool => $this->courseMatchesEmployee($course, $employee));

        $assigned = 0;
        foreach ($courses as $course) {
            $existing = TrainingEnrollment::query()
                ->where('restaurant_id', $employee->restaurant_id)
                ->where('course_id', $course->id)
                ->where('employee_id', $employee->id)
                ->first();
            if ($existing && $existing->status !== 'failed' && ! $this->certificateExpired($existing)) {
                continue;
            }

            $this->assign(
                $course,
                [$employee->id],
                null,
                Carbon::parse($employee->hire_date ?: now())->addDays(max(1, (int) ($course->due_days ?? 14))),
                true,
                'Tự động onboarding theo vai trò/chi nhánh',
            );
            $assigned++;
        }

        return $assigned;
    }

    public function markLessonComplete(TrainingEnrollment $enrollment, TrainingLesson $lesson, ?User $actor = null): TrainingEnrollment
    {
        return DB::transaction(function () use ($enrollment, $lesson, $actor): TrainingEnrollment {
            $locked = TrainingEnrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->with('course.lessons', 'course.quizzes')
                ->firstOrFail();

            if ((int) $lesson->course_id !== (int) $locked->course_id) {
                throw new InvalidArgumentException('Bài học không thuộc khóa đào tạo này.');
            }
            if ($locked->status === 'cancelled' || $locked->is_overdue && $locked->mandatory) {
                throw new InvalidArgumentException('Khóa học đã quá hạn hoặc bị hủy, cần được giao lại.');
            }

            $completed = array_map('intval', $locked->completed_lessons ?? []);
            if (! in_array((int) $lesson->id, $completed, true)) {
                $completed[] = (int) $lesson->id;
            }

            $locked->update([
                'completed_lessons' => array_values(array_unique($completed)),
                'status' => 'in_progress',
                'started_at' => $locked->started_at ?? now(),
                'last_activity_at' => now(),
                'is_overdue' => false,
            ]);
            $locked->recalculateProgress();
            $this->log($locked, 'lesson_completed', $actor, ['lesson_id' => $lesson->id]);
            $this->tryComplete($locked->fresh('course.lessons', 'course.quizzes'), $actor);

            return $locked->fresh();
        });
    }

    public function submitQuiz(TrainingEnrollment $enrollment, TrainingQuiz $quiz, array $answers, ?User $actor = null): array
    {
        return DB::transaction(function () use ($enrollment, $quiz, $answers, $actor): array {
            $locked = TrainingEnrollment::query()
                ->whereKey($enrollment->id)
                ->lockForUpdate()
                ->with('course.lessons', 'course.quizzes')
                ->firstOrFail();
            if ((int) $quiz->course_id !== (int) $locked->course_id) {
                throw new InvalidArgumentException('Bài kiểm tra không thuộc khóa đào tạo này.');
            }
            if ($locked->status === 'cancelled' || $locked->is_overdue && $locked->mandatory) {
                throw new InvalidArgumentException('Khóa học đã quá hạn hoặc bị hủy, cần được giao lại.');
            }

            $attempts = TrainingQuizAttempt::query()
                ->where('enrollment_id', $locked->id)
                ->where('quiz_id', $quiz->id)
                ->lockForUpdate()
                ->count();
            if ($attempts >= (int) $quiz->max_attempts) {
                throw new InvalidArgumentException('Đã hết lượt làm bài kiểm tra.');
            }

            $correct = 0;
            $questions = $quiz->questions ?? [];
            foreach ($questions as $index => $question) {
                if (array_key_exists($index, $answers) && (int) $answers[$index] === (int) ($question['correct'] ?? -1)) {
                    $correct++;
                }
            }
            $score = count($questions) > 0 ? (int) round(($correct / count($questions)) * 100) : 0;
            $passed = $score >= (int) $quiz->pass_score;
            TrainingQuizAttempt::create([
                'branch_id' => $locked->branch_id,
                'enrollment_id' => $locked->id,
                'quiz_id' => $quiz->id,
                'score' => $score,
                'passed' => $passed,
                'answers' => $answers,
            ]);

            $locked->update([
                'status' => $passed ? 'in_progress' : ($attempts + 1 >= (int) $quiz->max_attempts ? 'failed' : 'in_progress'),
                'last_score' => $score,
                'last_activity_at' => now(),
                'started_at' => $locked->started_at ?? now(),
            ]);
            $locked->recalculateProgress();
            $this->log($locked, $passed ? 'quiz_passed' : 'quiz_failed', $actor, [
                'quiz_id' => $quiz->id,
                'score' => $score,
                'attempt' => $attempts + 1,
            ]);
            $completed = $this->tryComplete($locked->fresh('course.lessons', 'course.quizzes'), $actor);
            $finalEnrollment = $completed ?? $locked->fresh();

            return [
                'score' => $score,
                'passed' => $passed,
                'correct' => $correct,
                'total' => count($questions),
                'attempts_left' => max(0, (int) $quiz->max_attempts - $attempts - 1),
                'progress' => (int) $finalEnrollment->progress_percent,
                'status' => $finalEnrollment->status,
                'certificate_code' => $completed?->certificate_code,
                'awaiting_manager_approval' => (bool) $completed?->awaiting_manager_approval,
            ];
        });
    }

    public function approveCompletion(TrainingEnrollment $enrollment, User $manager): TrainingEnrollment
    {
        return DB::transaction(function () use ($enrollment, $manager): TrainingEnrollment {
            $locked = TrainingEnrollment::query()->whereKey($enrollment->id)->lockForUpdate()->with('course')->firstOrFail();
            if (! $locked->course?->requires_manager_signoff) {
                throw new InvalidArgumentException('Khóa học này không yêu cầu quản lý ký duyệt.');
            }
            if ((int) $locked->branch_id !== (int) $manager->branch_id && ! $manager->canViewAllBranches()) {
                throw new InvalidArgumentException('Bạn không phụ trách chi nhánh của nhân viên này.');
            }
            if ((int) $locked->progress_percent < 100) {
                throw new InvalidArgumentException('Nhân viên chưa hoàn tất đủ nội dung khóa học.');
            }

            $locked->update([
                'manager_approved_by' => $manager->id,
                'manager_approved_at' => now(),
                'awaiting_manager_approval' => false,
            ]);
            $this->issueCertificate($locked, $manager);
            $this->log($locked, 'manager_approved', $manager);

            return $locked->fresh();
        });
    }

    public function syncDueStatuses(?int $restaurantId = null): int
    {
        $query = TrainingEnrollment::query()
            ->whereIn('status', ['enrolled', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('is_overdue', false)
            ->when($restaurantId, fn ($q) => $q->where('restaurant_id', $restaurantId));

        $count = 0;
        $query->orderBy('id')->chunkById(200, function (Collection $rows) use (&$count): void {
            foreach ($rows as $enrollment) {
                $enrollment->update(['is_overdue' => true]);
                $this->log($enrollment, 'overdue', null, ['due_at' => $enrollment->due_at?->toIso8601String()]);
                $count++;
            }
        });

        return $count;
    }

    public function complianceSummary(int $restaurantId, ?int $branchId = null): array
    {
        $base = TrainingEnrollment::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId));

        return [
            'assigned' => (clone $base)->whereIn('status', ['enrolled', 'in_progress'])->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'overdue' => (clone $base)->where('is_overdue', true)->count(),
            'awaiting_approval' => (clone $base)->where('awaiting_manager_approval', true)->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'certificates_expiring' => (clone $base)->whereNotNull('certificate_expires_at')->whereBetween('certificate_expires_at', [now(), now()->addDays(30)])->count(),
        ];
    }

    private function tryComplete(TrainingEnrollment $enrollment, ?User $actor): ?TrainingEnrollment
    {
        $course = $enrollment->course;
        $requiredLessons = $course->lessons->where('is_required', true);
        $requiredLessons = $requiredLessons->isEmpty() ? $course->lessons : $requiredLessons;
        $completedLessons = collect($enrollment->completed_lessons ?? [])->map(fn ($id) => (int) $id);
        if ($requiredLessons->contains(fn (TrainingLesson $lesson): bool => ! $completedLessons->contains((int) $lesson->id))) {
            return null;
        }

        foreach ($course->quizzes->where('is_required', true) as $quiz) {
            if (! $enrollment->quizAttempts()->where('quiz_id', $quiz->id)->where('passed', true)->exists()) {
                return null;
            }
        }

        if ($course->requires_manager_signoff && ! $enrollment->manager_approved_at) {
            $enrollment->update(['awaiting_manager_approval' => true, 'status' => 'in_progress']);

            return $enrollment->fresh();
        }

        $this->issueCertificate($enrollment, $actor);

        return $enrollment->fresh();
    }

    private function issueCertificate(TrainingEnrollment $enrollment, ?User $actor): void
    {
        $course = $enrollment->course;
        $code = $enrollment->certificate_code;
        if (! $code) {
            do {
                $code = 'CERT-'.strtoupper(Str::random(8));
            } while (TrainingEnrollment::where('certificate_code', $code)->exists());
        }

        $issuedAt = $enrollment->certificate_issued_at ?? now();
        $enrollment->update([
            'status' => 'completed',
            'progress_percent' => 100,
            'completed_at' => $enrollment->completed_at ?? now(),
            'certificate_code' => $code,
            'certificate_issued_at' => $issuedAt,
            'certificate_expires_at' => $course->validity_days ? $issuedAt->copy()->addDays((int) $course->validity_days) : null,
            'awaiting_manager_approval' => false,
            'is_overdue' => false,
            'last_activity_at' => now(),
        ]);
        $this->log($enrollment, 'completed', $actor, ['certificate_code' => $code]);
    }

    private function log(TrainingEnrollment $enrollment, string $activity, ?User $actor, array $metadata = []): void
    {
        TrainingActivityLog::create([
            'restaurant_id' => $enrollment->restaurant_id,
            'enrollment_id' => $enrollment->id,
            'actor_id' => $actor?->id,
            'activity' => $activity,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    private function courseMatchesEmployee(TrainingCourse $course, Employee $employee): bool
    {
        $roles = array_values(array_filter($course->target_roles ?? []));
        $branches = array_map('intval', array_values(array_filter($course->target_branch_ids ?? [])));
        $employeeRole = $employee->user?->roles?->first()?->name ?? $employee->role?->name;

        return ($course->branch_id === null || (int) $course->branch_id === (int) $employee->branch_id)
            && (empty($roles) || in_array($employeeRole, $roles, true))
            && (empty($branches) || in_array((int) $employee->branch_id, $branches, true));
    }

    private function certificateExpired(TrainingEnrollment $enrollment): bool
    {
        return $enrollment->certificate_expires_at !== null && $enrollment->certificate_expires_at->isPast();
    }
}
