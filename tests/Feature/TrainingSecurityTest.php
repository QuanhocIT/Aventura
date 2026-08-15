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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_employee_cannot_manage_training_courses(): void
    {
        $restaurant = Restaurant::factory()->create();
        $employee = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status' => 'active',
        ]);
        $employee->assignRole('waiter');

        $this->actingAs($employee)
            ->post(route('training.courses.store'), [
                'title' => 'Không được tự tạo khóa',
                'type' => 'custom',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('training_courses', [
            'restaurant_id' => $restaurant->id,
            'title' => 'Không được tự tạo khóa',
        ]);
    }

    public function test_employee_cannot_complete_a_lesson_or_quiz_from_another_course(): void
    {
        $restaurant = Restaurant::factory()->create();
        $branch = RestaurantBranch::factory()->create(['restaurant_id' => $restaurant->id]);
        $learner = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $learner->assignRole('waiter');

        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
            'user_id' => $learner->id,
        ]);

        $course = TrainingCourse::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Khóa được giao',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $otherCourse = TrainingCourse::create([
            'restaurant_id' => $restaurant->id,
            'title' => 'Khóa khác',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $otherLesson = TrainingLesson::create([
            'course_id' => $otherCourse->id,
            'title' => 'Bài của khóa khác',
            'content_type' => 'text',
        ]);
        $otherQuiz = TrainingQuiz::create([
            'course_id' => $otherCourse->id,
            'title' => 'Bài kiểm tra của khóa khác',
            'pass_score' => 70,
            'max_attempts' => 3,
            'questions' => [[
                'question' => '1 + 1 = ?',
                'options' => ['1', '2'],
                'correct' => 1,
            ]],
        ]);
        $enrollment = TrainingEnrollment::create([
            'restaurant_id' => $restaurant->id,
            'course_id' => $course->id,
            'employee_id' => $employee->id,
            'status' => 'enrolled',
        ]);

        $this->actingAs($learner)
            ->postJson(route('training.complete-lesson'), [
                'enrollment_id' => $enrollment->id,
                'lesson_id' => $otherLesson->id,
            ])
            ->assertStatus(422);

        $this->actingAs($learner)
            ->postJson(route('training.submit-quiz'), [
                'enrollment_id' => $enrollment->id,
                'quiz_id' => $otherQuiz->id,
                'answers' => [1],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('training_quiz_attempts', [
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $otherQuiz->id,
        ]);
    }
}
