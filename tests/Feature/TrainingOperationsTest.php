<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingQuiz;
use App\Models\User;
use App\Services\TrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        $this->restaurant = Restaurant::factory()->create();
        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'active',
        ]);
        $this->owner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $this->owner->assignRole($ownerRole);
        $this->restaurant->update(['owner_user_id' => $this->owner->id]);

        $this->actingAs($this->owner);
    }

    public function test_required_course_is_automatically_assigned_to_matching_new_hire(): void
    {
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Onboarding kho',
            'type' => 'onboarding',
            'is_active' => true,
            'required_for_new_hires' => true,
            'due_days' => 7,
            'target_roles' => ['waiter'],
            'target_branch_ids' => [$this->branch->id],
        ]);
        $learner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $learner->assignRole('waiter');
        $employee = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $learner->id,
            'hire_date' => now()->toDateString(),
        ]);

        $assigned = app(TrainingService::class)->autoAssignRequiredCourses($employee);

        $this->assertSame(1, $assigned);
        $this->assertDatabaseHas('training_enrollments', [
            'restaurant_id' => $this->restaurant->id,
            'course_id' => $course->id,
            'employee_id' => $employee->id,
            'mandatory' => true,
            'status' => 'enrolled',
        ]);
        $this->assertNotNull(TrainingEnrollment::where('course_id', $course->id)->first()->due_at);
    }

    public function test_learner_can_complete_lesson_and_quiz_and_receive_certificate(): void
    {
        $learner = $this->createLearner();
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'An toàn thực phẩm',
            'type' => 'attp',
            'is_active' => true,
            'validity_days' => 365,
        ]);
        $lesson = TrainingLesson::create([
            'course_id' => $course->id,
            'title' => 'Quy trình vệ sinh',
            'content_type' => 'text',
            'content' => 'Nội dung đào tạo nội bộ',
            'is_required' => true,
        ]);
        $quiz = TrainingQuiz::create([
            'course_id' => $course->id,
            'title' => 'Kiểm tra cuối khóa',
            'pass_score' => 70,
            'max_attempts' => 2,
            'is_required' => true,
            'questions' => [[
                'question' => 'Nhiệt độ bảo quản lạnh?',
                'options' => ['0-5°C', '20-25°C'],
                'correct' => 0,
            ]],
        ]);
        $enrollment = app(TrainingService::class)->assign($course, [$learner->employee->id], $this->owner)->first();

        $content = $this->actingAs($learner)
            ->getJson(route('training.courses.content', $course))
            ->assertOk();

        $this->assertSame($enrollment->id, $content->json('enrollment.id'));
        $this->assertSame($course->id, $content->json('enrollment.course_id'));
        $this->assertNull($content->json('quizzes.0.questions.0.correct'));

        $this->postJson(route('training.complete-lesson'), [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ])->assertOk()->assertJsonPath('progress', 50);

        $this->postJson(route('training.submit-quiz'), [
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'answers' => [0],
        ])->assertOk()->assertJsonPath('passed', true)->assertJsonPath('progress', 100);

        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => 'completed',
            'progress_percent' => 100,
        ]);
        $this->assertNotNull(TrainingEnrollment::find($enrollment->id)->certificate_code);
        $this->assertDatabaseHas('training_activity_logs', [
            'enrollment_id' => $enrollment->id,
            'activity' => 'completed',
        ]);
    }

    public function test_manager_signoff_is_required_before_certificate_is_issued(): void
    {
        $learner = $this->createLearner();
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Vận hành ca làm',
            'type' => 'operations',
            'is_active' => true,
            'requires_manager_signoff' => true,
        ]);
        $lesson = TrainingLesson::create([
            'course_id' => $course->id,
            'title' => 'Bàn giao ca',
            'content_type' => 'text',
            'is_required' => true,
        ]);
        $quiz = TrainingQuiz::create([
            'course_id' => $course->id,
            'title' => 'Đánh giá vận hành',
            'pass_score' => 1,
            'max_attempts' => 1,
            'is_required' => true,
            'questions' => [[
                'question' => 'Đã kiểm tra checklist?',
                'options' => ['Có', 'Không'],
                'correct' => 0,
            ]],
        ]);
        $enrollment = app(TrainingService::class)->assign($course, [$learner->employee->id], $this->owner)->first();

        $this->actingAs($learner)->postJson(route('training.complete-lesson'), [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ])->assertOk();
        $quizResponse = $this->postJson(route('training.submit-quiz'), [
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'answers' => [0],
        ])->assertOk();

        $quizResponse->assertJsonPath('awaiting_manager_approval', true);
        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => 'in_progress',
            'awaiting_manager_approval' => true,
        ]);

        $this->actingAs($this->owner)
            ->post(route('training.enrollments.approve', $enrollment))
            ->assertRedirect();

        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => 'completed',
            'awaiting_manager_approval' => false,
            'manager_approved_by' => $this->owner->id,
        ]);
    }

    public function test_due_enrollment_is_marked_overdue_and_logged(): void
    {
        $learner = $this->createLearner();
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Đào tạo bắt buộc',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $enrollment = app(TrainingService::class)->assign(
            $course,
            [$learner->employee->id],
            $this->owner,
            Carbon::now()->subHour(),
            true,
            'Kiểm tra quá hạn',
        )->first();

        $count = app(TrainingService::class)->syncDueStatuses($this->restaurant->id);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'is_overdue' => true,
        ]);
        $this->assertDatabaseHas('training_activity_logs', [
            'enrollment_id' => $enrollment->id,
            'activity' => 'overdue',
        ]);
    }

    public function test_reassigning_a_failed_course_resets_old_progress_and_attempts(): void
    {
        $learner = $this->createLearner();
        $course = TrainingCourse::create([
            'restaurant_id' => $this->restaurant->id,
            'title' => 'Đào tạo kiểm tra lại',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $quiz = TrainingQuiz::create([
            'course_id' => $course->id,
            'title' => 'Bài kiểm tra bắt buộc',
            'pass_score' => 100,
            'max_attempts' => 1,
            'is_required' => true,
            'questions' => [[
                'question' => 'Đáp án đúng?',
                'options' => ['A', 'B'],
                'correct' => 0,
            ]],
        ]);
        $service = app(TrainingService::class);
        $enrollment = $service->assign($course, [$learner->employee->id], $this->owner)->first();

        $this->actingAs($learner)->postJson(route('training.submit-quiz'), [
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'answers' => [1],
        ])->assertOk()->assertJsonPath('passed', false);
        $this->assertDatabaseHas('training_enrollments', ['id' => $enrollment->id, 'status' => 'failed']);

        $service->assign($course, [$learner->employee->id], $this->owner, null, true, 'Giao học lại');

        $this->assertDatabaseHas('training_enrollments', [
            'id' => $enrollment->id,
            'status' => 'enrolled',
            'progress_percent' => 0,
            'certificate_code' => null,
        ]);
        $this->assertDatabaseCount('training_quiz_attempts', 0);
    }

    private function createLearner(): User
    {
        $learner = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        $learner->assignRole('waiter');
        Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'user_id' => $learner->id,
        ]);

        return $learner->fresh('employee');
    }
}
