<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulesAdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $employeeUser1;

    private User $employeeUser2;

    private Employee $employee1;

    private Employee $employee2;

    private Restaurant $restaurant;

    private RestaurantBranch $branch;

    private WorkShift $shift1;

    private WorkShift $shift2;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $employeeRole = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        $this->owner = User::factory()->create(['status' => 'active']);
        $this->owner->assignRole($ownerRole);

        $this->restaurant = Restaurant::factory()->create([
            'owner_user_id' => $this->owner->id,
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'checkin_radius_meters' => 1000, // wider radius for test stability
        ]);
        $this->owner->update(['restaurant_id' => $this->restaurant->id]);

        $this->branch = RestaurantBranch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'manager_user_id' => $this->owner->id,
        ]);
        $this->owner->update(['branch_id' => $this->branch->id]);

        // Create two employees
        $this->employeeUser1 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->employeeUser1->assignRole($employeeRole);
        $this->employee1 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->employeeUser1->id,
            'role_id' => 1, // Same role
            'job_title' => 'Waiter',
            'status' => 'active',
        ]);

        $this->employeeUser2 = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'status' => 'active']);
        $this->employeeUser2->assignRole($employeeRole);
        $this->employee2 = Employee::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->employeeUser2->id,
            'role_id' => 1, // Same role
            'job_title' => 'Waiter',
            'status' => 'active',
        ]);

        $this->shift1 = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Morning Shift',
            'code' => 'MORNING_SHIFT',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'status' => 'active',
        ]);

        $this->shift2 = WorkShift::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Evening Shift',
            'code' => 'EVENING_SHIFT',
            'start_time' => '16:00:00',
            'end_time' => '23:00:00',
            'status' => 'active',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test AI Swap Suggestions Ranking and Compatibility.
     */
    public function test_ai_swap_suggestions_ranking(): void
    {
        $myAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift1->id,
            'scheduled_date' => '2026-06-08', // Monday
            'status' => 'scheduled',
        ]);

        // Candidate 1: Colleague with matching role (Waiter)
        $cand1 = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee2->id,
            'shift_id' => $this->shift2->id,
            'scheduled_date' => '2026-06-09', // Tuesday
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->employeeUser1);
        $response = $this->getJson(route('schedules.swap-suggestions', ['assignment_id' => $myAssignment->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'suggestions' => [
                '*' => ['id', 'employee_name', 'shift_name', 'shift_time', 'day', 'date', 'score', 'reasons'],
            ],
        ]);

        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        // Colleague 2 should have score >= 50 because they share the same role/job title
        $this->assertEquals($cand1->id, $suggestions[0]['id']);
        $this->assertGreaterThanOrEqual(50, $suggestions[0]['score']);
    }

    /**
     * Test check-in photo storage and path persistence.
     */
    public function test_checkin_photo_upload_persistence(): void
    {
        // Fake local disk checkins folder
        Storage::fake('public');

        $assignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift1->id,
            'scheduled_date' => today()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Base64 mock image of 1x1 pixel JPEG
        $base64Photo = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';

        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.check-in'), [
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'check_in_photo' => $base64Photo,
        ]);

        $response->assertSessionHasNoErrors();
        $assignment->refresh();

        $this->assertEquals('checked_in', $assignment->status);
        $this->assertNotNull($assignment->check_in_photo_path);

        // Assert that the file is created in public disk
        Storage::disk('public')->assertExists($assignment->check_in_photo_path);
    }

    /**
     * Test notification creation and management.
     */
    public function test_notifications_logging_and_read_marker(): void
    {
        $myAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee1->id,
            'shift_id' => $this->shift1->id,
            'scheduled_date' => '2026-06-08',
            'status' => 'scheduled',
        ]);

        $otherAssignment = ScheduleAssignment::create([
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
            'employee_id' => $this->employee2->id,
            'shift_id' => $this->shift2->id,
            'scheduled_date' => '2026-06-09',
            'status' => 'scheduled',
        ]);

        // Act: Employee 1 initiates a swap request with Employee 2
        $this->actingAs($this->employeeUser1);
        $response = $this->post(route('schedules.swap.request'), [
            'requester_assignment_id' => $myAssignment->id,
            'receiver_assignment_id' => $otherAssignment->id,
            'notes' => 'Request swap test',
        ]);

        $response->assertSessionHasNoErrors();

        // Employee 2 should have 1 unread notification now
        $this->assertEquals(1, $this->employeeUser2->unreadNotifications()->count());
        $notification = $this->employeeUser2->unreadNotifications()->first();

        // Assert Notification API retrieval
        $this->actingAs($this->employeeUser2);
        $getNotifRes = $this->getJson(route('notifications.index'));
        $getNotifRes->assertStatus(200);
        $getNotifRes->assertJsonFragment([
            'id' => $notification->id,
            'message' => "Đồng nghiệp {$this->employee1->full_name} đề xuất đổi ca trực tuần này với bạn.",
        ]);

        // Assert marking as read works
        $readRes = $this->postJson(route('notifications.read', ['id' => $notification->id]));
        $readRes->assertStatus(200);
        $this->assertEquals(0, $this->employeeUser2->unreadNotifications()->count());
    }
}
