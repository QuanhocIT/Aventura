<?php

namespace App\Http\Controllers;

use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingQuiz;
use App\Models\TrainingQuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (!$restaurant && !$request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && !app(\App\Services\QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_timekeeping',
                'feature_label' => 'Đào tạo Nhân viên',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;

        $courses = TrainingCourse::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->withCount(['lessons', 'enrollments'])
            ->with('quizzes:id,course_id,title')
            ->orderBy('sort_order')
            ->get();

        $enrollments = TrainingEnrollment::where('restaurant_id', $restaurantId)
            ->with(['employee:id,full_name', 'course:id,title'])
            ->latest()
            ->take(50)
            ->get();

        $stats = [
            'total_courses' => $courses->count(),
            'total_enrollments' => TrainingEnrollment::where('restaurant_id', $restaurantId)->count(),
            'completed' => TrainingEnrollment::where('restaurant_id', $restaurantId)->where('status', 'completed')->count(),
            'in_progress' => TrainingEnrollment::where('restaurant_id', $restaurantId)->where('status', 'in_progress')->count(),
        ];

        return Inertia::render('training/Index', compact('courses', 'enrollments', 'stats'));
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:onboarding,menu,attp,operations,custom'],
            'is_required' => ['boolean'],
        ]);

        TrainingCourse::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'is_required' => $data['is_required'] ?? false,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Đã tạo khóa đào tạo \"{$data['title']}\".");
    }

    public function storeLesson(Request $request, TrainingCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'in:text,video,pdf,link'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        $fileUrl = null;
        if ($request->hasFile('file')) {
            $fileUrl = $request->file('file')->store('training', 'public');
        }

        TrainingLesson::create([
            'course_id' => $course->id,
            'title' => $data['title'],
            'content_type' => $data['content_type'],
            'content' => $data['content'] ?? null,
            'file_url' => $fileUrl,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'sort_order' => $course->lessons()->count(),
        ]);

        return back()->with('success', "Đã thêm bài học \"{$data['title']}\".");
    }

    public function storeQuiz(Request $request, TrainingCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pass_score' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.options' => ['required', 'array', 'min:2'],
            'questions.*.correct' => ['required', 'integer', 'min:0'],
        ]);

        TrainingQuiz::create([
            'course_id' => $course->id,
            'title' => $data['title'],
            'pass_score' => $data['pass_score'],
            'max_attempts' => $data['max_attempts'],
            'questions' => $data['questions'],
        ]);

        return back()->with('success', "Đã tạo bài kiểm tra \"{$data['title']}\".");
    }

    public function enrollEmployee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', "exists:training_courses,id,restaurant_id,{$request->user()->restaurant_id}"],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ["exists:employees,id,restaurant_id,{$request->user()->restaurant_id}"],
        ]);

        $enrolled = 0;
        foreach ($data['employee_ids'] as $empId) {
            $exists = TrainingEnrollment::where('course_id', $data['course_id'])
                ->where('employee_id', $empId)
                ->exists();

            if (! $exists) {
                TrainingEnrollment::create([
                    'restaurant_id' => $request->user()->restaurant_id,
                    'course_id' => $data['course_id'],
                    'employee_id' => $empId,
                    'status' => 'enrolled',
                ]);
                $enrolled++;
            }
        }

        return back()->with('success', "Đã ghi danh {$enrolled} nhân viên.");
    }

    public function completeLesson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', "exists:training_enrollments,id,restaurant_id,{$request->user()->restaurant_id}"],
            'lesson_id' => ['required', 'exists:training_lessons,id'],
        ]);

        $enrollment = TrainingEnrollment::findOrFail($data['enrollment_id']);
        $completed = $enrollment->completed_lessons ?? [];

        if (! in_array($data['lesson_id'], $completed)) {
            $completed[] = (int) $data['lesson_id'];
            $enrollment->update([
                'completed_lessons' => $completed,
                'status' => 'in_progress',
                'started_at' => $enrollment->started_at ?? now(),
            ]);
            $enrollment->recalculateProgress();
        }

        return response()->json(['success' => true, 'progress' => $enrollment->progress_percent]);
    }

    public function submitQuiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', 'exists:training_enrollments,id'],
            'quiz_id' => ['required', 'exists:training_quizzes,id'],
            'answers' => ['required', 'array'],
        ]);

        $enrollment = TrainingEnrollment::findOrFail($data['enrollment_id']);
        $quiz = TrainingQuiz::findOrFail($data['quiz_id']);

        // Atomic check with lock to prevent race condition on max_attempts
        $attempts = \Illuminate\Support\Facades\DB::transaction(function () use ($enrollment, $quiz) {
            return TrainingQuizAttempt::lockForUpdate()
                ->where('enrollment_id', $enrollment->id)
                ->where('quiz_id', $quiz->id)
                ->count();
        });

        if ($attempts >= $quiz->max_attempts) {
            return response()->json(['message' => 'Đã hết lượt làm bài.'], 422);
        }

        // Grade
        $correct = 0;
        $questions = $quiz->questions;
        foreach ($questions as $idx => $q) {
            if (isset($data['answers'][$idx]) && (int) $data['answers'][$idx] === (int) $q['correct']) {
                $correct++;
            }
        }

        $score = count($questions) > 0 ? (int) round(($correct / count($questions)) * 100) : 0;
        $passed = $score >= $quiz->pass_score;

        $attempt = TrainingQuizAttempt::create([
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'passed' => $passed,
            'answers' => $data['answers'],
        ]);

        if ($passed && $enrollment->progress_percent >= 100) {
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'certificate_code' => 'CERT-' . strtoupper(Str::random(8)),
            ]);
        } elseif (! $passed && $attempts + 1 >= $quiz->max_attempts) {
            $enrollment->update(['status' => 'failed']);
        }

        return response()->json([
            'score' => $score,
            'passed' => $passed,
            'correct' => $correct,
            'total' => count($questions),
            'attempts_left' => $quiz->max_attempts - $attempts - 1,
            'certificate_code' => $passed ? $enrollment->fresh()->certificate_code : null,
        ]);
    }

    public function destroyCourse(TrainingCourse $course): RedirectResponse
    {
        $course->delete();

        return back()->with('success', 'Đã xóa khóa đào tạo.');
    }
}
