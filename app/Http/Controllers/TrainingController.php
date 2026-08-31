<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RestaurantBranch;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingQuiz;
use App\Services\QuotaService;
use App\Services\TrainingService;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $trainingService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $restaurant = $request->user()->restaurant;
        if (! $restaurant && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Không tìm thấy nhà hàng.');
        }
        $restaurant?->loadMissing('plan');
        if ($restaurant && ! app(QuotaService::class)->hasFeature($restaurant, 'hr_timekeeping')) {
            return Inertia::render('FeatureGate', [
                'feature' => 'hr_timekeeping',
                'feature_label' => 'Đào tạo Nhân viên',
                'plan_name' => $restaurant->plan?->name ?? 'Miễn Phí',
                'required_plan' => 'Cơ Bản',
            ]);
        }

        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();
        $this->trainingService->syncDueStatuses($restaurantId);
        $canManage = $request->user()->isOwner()
            || $request->user()->isSuperAdmin()
            || $request->user()->can('training.manage');

        $courses = TrainingCourse::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->withCount(['lessons', 'enrollments'])
            ->with(['quizzes:id,course_id,title,is_required,pass_score,max_attempts', 'lessons:id,course_id,title,content_type,duration_minutes,is_required'])
            ->orderBy('sort_order')
            ->get()
            ->filter(function (TrainingCourse $course) use ($branchId): bool {
                if ($branchId === null) {
                    return true;
                }

                if ($course->branch_id !== null) {
                    return (int) $course->branch_id === (int) $branchId;
                }

                $targetBranches = array_map('intval', $course->target_branch_ids ?? []);

                return empty($targetBranches) || in_array((int) $branchId, $targetBranches, true);
            })
            ->values();

        $enrollments = TrainingEnrollment::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $canManage, function ($query) use ($request): void {
                $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('user_id', $request->user()->id));
            })
            ->with(['employee:id,full_name,branch_id', 'course:id,title,type,requires_manager_signoff', 'assignedBy:id,name', 'managerApprovedBy:id,name'])
            ->latest()
            ->take(50)
            ->get();

        $employees = $canManage
            ? Employee::query()->where('restaurant_id', $restaurantId)->where('status', 'active')
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->with('branch:id,name', 'user.roles')->orderBy('full_name')->get(['id', 'full_name', 'branch_id', 'user_id'])->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'branch_id' => $employee->branch_id,
                'branch_name' => $employee->branch?->name,
                'role' => $employee->user?->roles?->first()?->name ?? $employee->role?->name,
            ])->values()
            : [];
        $branches = $canManage
            ? RestaurantBranch::where('restaurant_id', $restaurantId)->where('status', 'active')
                ->when($branchId !== null, fn ($query) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name'])
            : [];

        $stats = [
            'total_courses' => $courses->count(),
            'total_enrollments' => TrainingEnrollment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->count(),
            'completed' => TrainingEnrollment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'completed')->count(),
            'in_progress' => TrainingEnrollment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'in_progress')->count(),
            ...$this->trainingService->complianceSummary($restaurantId, $branchId),
        ];

        return Inertia::render('training/Index', [
            'courses' => $courses,
            'enrollments' => $enrollments,
            'employees' => $employees,
            'branches' => $branches,
            'stats' => $stats,
            'canManage' => $canManage,
            'currentEmployeeId' => Employee::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->where('user_id', $request->user()->id)->value('id'),
            'branchContext' => $this->tenantContext->toArray(),
        ]);
    }

    public function courseContent(Request $request, TrainingCourse $course): JsonResponse
    {
        $this->assertCourseBelongsToTenant($request, $course);
        $this->assertCourseScope($course);
        $employee = Employee::where('restaurant_id', $request->user()->restaurant_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $enrollment = TrainingEnrollment::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->when($this->tenantContext->isBranchScoped(), fn ($query) => $query->where('branch_id', $this->tenantContext->activeBranchId()))
            ->where('course_id', $course->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return response()->json([
            'course' => $course->only(['id', 'title', 'description', 'type', 'requires_manager_signoff']),
            'enrollment' => $enrollment->only(['id', 'course_id', 'status', 'progress_percent', 'completed_lessons', 'due_at', 'is_overdue', 'awaiting_manager_approval', 'certificate_code']),
            'lessons' => $course->lessons()->get(['id', 'course_id', 'title', 'content_type', 'content', 'file_url', 'duration_minutes', 'is_required']),
            'quizzes' => $course->quizzes()->get()->map(fn (TrainingQuiz $quiz): array => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'pass_score' => $quiz->pass_score,
                'max_attempts' => $quiz->max_attempts,
                'questions' => collect($quiz->questions ?? [])->map(fn (array $question): array => [
                    'question' => $question['question'] ?? '',
                    'options' => $question['options'] ?? [],
                ])->values(),
            ])->values(),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:onboarding,menu,attp,operations,custom'],
            'is_required' => ['boolean'],
            'course_code' => ['nullable', 'string', 'max:40'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'max:60'],
            'target_branch_ids' => ['nullable', 'array'],
            'target_branch_ids.*' => [TenantRule::exists('restaurant_branches')],
            'required_for_new_hires' => ['boolean'],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'requires_manager_signoff' => ['boolean'],
        ]);

        TrainingCourse::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'course_code' => ($data['course_code'] ?? null) ?: 'TRN-'.strtoupper(Str::random(6)),
            'version' => '1.0',
            'type' => $data['type'],
            'is_required' => $data['is_required'] ?? false,
            'target_roles' => array_values($data['target_roles'] ?? []),
            'target_branch_ids' => array_values(array_map('intval', $data['target_branch_ids'] ?? [])),
            'required_for_new_hires' => $data['required_for_new_hires'] ?? false,
            'due_days' => $data['due_days'] ?? 14,
            'validity_days' => $data['validity_days'] ?? null,
            'requires_manager_signoff' => $data['requires_manager_signoff'] ?? false,
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Đã tạo khóa đào tạo \"{$data['title']}\".");
    }

    public function storeLesson(Request $request, TrainingCourse $course): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->assertCourseBelongsToTenant($request, $course);
        $this->assertCourseScope($course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'in:text,video,pdf,link'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['boolean'],
            'requires_acknowledgement' => ['boolean'],
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
            'is_required' => $data['is_required'] ?? true,
            'requires_acknowledgement' => $data['requires_acknowledgement'] ?? false,
            'sort_order' => $course->lessons()->count(),
        ]);

        return back()->with('success', "Đã thêm bài học \"{$data['title']}\".");
    }

    public function storeQuiz(Request $request, TrainingCourse $course): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->assertCourseBelongsToTenant($request, $course);
        $this->assertCourseScope($course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pass_score' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'is_required' => ['boolean'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'randomize_questions' => ['boolean'],
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
            'is_required' => $data['is_required'] ?? true,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'randomize_questions' => $data['randomize_questions'] ?? false,
            'questions' => $data['questions'],
        ]);

        return back()->with('success', "Đã tạo bài kiểm tra \"{$data['title']}\".");
    }

    public function enrollEmployee(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'course_id' => ['required', "exists:training_courses,id,restaurant_id,{$request->user()->restaurant_id}"],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ["exists:employees,id,restaurant_id,{$request->user()->restaurant_id}"],
            'due_at' => ['nullable', 'date'],
            'mandatory' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $course = TrainingCourse::where('restaurant_id', $request->user()->restaurant_id)->findOrFail($data['course_id']);
        $this->assertCourseScope($course);

        $employees = Employee::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->when($this->tenantContext->isBranchScoped(), fn ($query) => $query->where('branch_id', $this->tenantContext->activeBranchId()))
            ->whereIn('id', $data['employee_ids'])
            ->get(['id', 'branch_id']);

        abort_unless(
            $request->user()->isSuperAdmin()
                || $request->user()->canViewAllBranches()
                || $employees->every(fn (Employee $employee): bool => $request->user()->canAccessBranch((int) $employee->branch_id)),
            403,
            'Bạn chỉ có thể ghi danh nhân viên thuộc chi nhánh được phân công.'
        );

        $enrollments = $this->trainingService->assign(
            $course,
            $data['employee_ids'],
            $request->user(),
            isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
            $data['mandatory'] ?? null,
            $data['reason'] ?? 'Giao đào tạo thủ công',
        );

        return back()->with('success', "Đã giao khóa học cho {$enrollments->count()} nhân viên.");
    }

    public function completeLesson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', "exists:training_enrollments,id,restaurant_id,{$request->user()->restaurant_id}"],
            'lesson_id' => ['required', 'exists:training_lessons,id'],
        ]);

        $enrollment = TrainingEnrollment::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->with('employee')
            ->findOrFail($data['enrollment_id']);
        $this->authorizeEnrollmentParticipant($request, $enrollment);

        $lesson = TrainingLesson::query()
            ->whereKey($data['lesson_id'])
            ->whereHas('course', fn ($query) => $query->where('restaurant_id', $request->user()->restaurant_id))
            ->firstOrFail();

        abort_unless(
            (int) $lesson->course_id === (int) $enrollment->course_id,
            422,
            'Bài học không thuộc khóa đào tạo của đăng ký này.'
        );
        try {
            $enrollment = $this->trainingService->markLessonComplete($enrollment, $lesson, $request->user());
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'progress' => $enrollment->progress_percent, 'status' => $enrollment->status, 'certificate_code' => $enrollment->certificate_code]);
    }

    public function submitQuiz(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_id' => ['required', TenantRule::exists('training_enrollments')],
            'quiz_id' => ['required', 'exists:training_quizzes,id'],
            'answers' => ['required', 'array'],
        ]);

        $enrollment = TrainingEnrollment::query()
            ->where('restaurant_id', $request->user()->restaurant_id)
            ->with('employee')
            ->findOrFail($data['enrollment_id']);
        $this->authorizeEnrollmentParticipant($request, $enrollment);

        $quiz = TrainingQuiz::query()
            ->whereKey($data['quiz_id'])
            ->whereHas('course', fn ($query) => $query->where('restaurant_id', $request->user()->restaurant_id))
            ->firstOrFail();

        abort_unless(
            (int) $quiz->course_id === (int) $enrollment->course_id,
            422,
            'Bài kiểm tra không thuộc khóa đào tạo của đăng ký này.'
        );

        try {
            return response()->json($this->trainingService->submitQuiz($enrollment, $quiz, $data['answers'], $request->user()));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function approveEnrollment(Request $request, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->assertEnrollmentBelongsToTenant($request, $enrollment);
        $this->assertEnrollmentScope($enrollment);
        $this->trainingService->approveCompletion($enrollment, $request->user());

        return back()->with('success', 'Đã ký duyệt hoàn thành đào tạo và phát hành chứng chỉ.');
    }

    public function syncEnrollment(Request $request, TrainingEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->assertEnrollmentBelongsToTenant($request, $enrollment);
        $this->assertEnrollmentScope($enrollment);
        $data = $request->validate([
            'due_at' => ['required', 'date'],
            'mandatory' => ['boolean'],
        ]);
        $enrollment->update([
            'due_at' => Carbon::parse($data['due_at']),
            'mandatory' => $data['mandatory'] ?? $enrollment->mandatory,
            'is_overdue' => false,
        ]);

        return back()->with('success', 'Đã cập nhật hạn đào tạo.');
    }

    public function destroyCourse(Request $request, TrainingCourse $course): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->assertCourseBelongsToTenant($request, $course);
        $this->assertCourseScope($course);

        $course->delete();

        return back()->with('success', 'Đã xóa khóa đào tạo.');
    }

    private function authorizeManagement(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isOwner() || $user->isSuperAdmin() || $user->can('training.manage')),
            403,
            'Bạn không có quyền quản lý đào tạo.'
        );
    }

    private function assertCourseBelongsToTenant(Request $request, TrainingCourse $course): void
    {
        abort_unless(
            $request->user()->isSuperAdmin()
                || (int) $course->restaurant_id === (int) $request->user()->restaurant_id,
            404,
            'Không tìm thấy khóa đào tạo.'
        );
    }

    private function assertCourseScope(TrainingCourse $course): void
    {
        if (! $this->tenantContext->isBranchScoped()) {
            return;
        }

        $branchId = (int) $this->tenantContext->activeBranchId();
        if ($course->branch_id !== null && (int) $course->branch_id !== $branchId) {
            abort(403, 'Khóa đào tạo không thuộc chi nhánh đang chọn.');
        }

        $targetBranches = array_map('intval', $course->target_branch_ids ?? []);
        abort_if(
            $course->branch_id === null && ! empty($targetBranches) && ! in_array($branchId, $targetBranches, true),
            403,
            'Khóa đào tạo không áp dụng cho chi nhánh đang chọn.',
        );
    }

    private function assertEnrollmentBelongsToTenant(Request $request, TrainingEnrollment $enrollment): void
    {
        abort_unless(
            $request->user()->isSuperAdmin()
                || (int) $enrollment->restaurant_id === (int) $request->user()->restaurant_id,
            404,
            'Không tìm thấy đăng ký đào tạo.'
        );
    }

    private function assertEnrollmentScope(TrainingEnrollment $enrollment): void
    {
        if ($this->tenantContext->isBranchScoped()) {
            $enrollmentBranchId = $enrollment->branch_id
                ?? $enrollment->employee?->branch_id
                ?? $enrollment->employee()->value('branch_id');

            abort_if(
                (int) $enrollmentBranchId !== (int) $this->tenantContext->activeBranchId(),
                403,
                'Đăng ký đào tạo không thuộc chi nhánh đang chọn.',
            );
        }
    }

    private function authorizeEnrollmentParticipant(Request $request, TrainingEnrollment $enrollment): void
    {
        $user = $request->user();
        $this->assertEnrollmentScope($enrollment);

        if ($user->isOwner() || $user->isSuperAdmin() || $user->can('training.manage')) {
            return;
        }

        abort_unless(
            (int) $enrollment->employee?->user_id === (int) $user->id,
            403,
            'Bạn chỉ có thể cập nhật tiến độ đào tạo của chính mình.'
        );
    }
}
