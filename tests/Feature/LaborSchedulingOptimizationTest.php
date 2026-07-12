<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Restaurant;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaborSchedulingOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionsSeeder']);
    }

    /**
     * Test the weekly scheduling page returns computed daily forecasts and staffing metrics.
     */
    public function test_weekly_scheduling_page_includes_daily_forecasts()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        // Create active work shifts
        $shift1 = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Ca Sáng (06:00 - 14:00)',
            'code'          => 'CA_SANG',
            'start_time'    => '06:00:00',
            'end_time'      => '14:00:00',
            'status'        => 'active',
        ]);

        $shift2 = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Ca Chiều (14:00 - 22:00)',
            'code'          => 'CA_CHIEU',
            'start_time'    => '14:00:00',
            'end_time'      => '22:00:00',
            'status'        => 'active',
        ]);

        // Access employees index page (EmployeeManagementController@employeesPage)
        $response = $this->actingAs($owner)->get(route('employees.index'));

        $response->assertStatus(200);

        // Verify dailyForecasts exists in the Inertia props
        $props = $response->original->getData()['page']['props'];
        $this->assertArrayHasKey('dailyForecasts', $props);

        $dailyForecasts = $props['dailyForecasts'];
        $this->assertNotEmpty($dailyForecasts);

        // Verify structure of the forecast for a day of the week
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->assertArrayHasKey($startOfWeek, $dailyForecasts);

        $forecast = $dailyForecasts[$startOfWeek];
        $this->assertArrayHasKey('condition', $forecast);
        $this->assertArrayHasKey('temperature', $forecast);
        $this->assertArrayHasKey('demand_level', $forecast);
        $this->assertArrayHasKey('demand_label', $forecast);
        $this->assertArrayHasKey('shifts', $forecast);

        // Verify shift suggestions structure inside dailyForecasts
        $this->assertNotEmpty($forecast['shifts']);
        $firstShiftSuggestion = $forecast['shifts'][0];
        $this->assertArrayHasKey('shift_id', $firstShiftSuggestion);
        $this->assertArrayHasKey('shift_name', $firstShiftSuggestion);
        $this->assertArrayHasKey('optimal_staff', $firstShiftSuggestion);
        $this->assertArrayHasKey('current_staff', $firstShiftSuggestion);
        $this->assertArrayHasKey('status', $firstShiftSuggestion);
    }

    /**
     * Test the replacement suggestions endpoint checks weekly shift counts and 11-hour rest rules.
     */
    public function test_replacement_suggestions_evaluates_overtime_and_rest_rules()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $restaurant = Restaurant::factory()->create([
            'owner_user_id' => $owner->id,
        ]);
        $owner->update(['restaurant_id' => $restaurant->id]);

        // Create role
        $role = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);

        // Create shift for target day (Morning shift: 08:00 - 16:00)
        $targetShift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Ca Sáng (08:00 - 16:00)',
            'code'          => 'CA_SANG_TEST',
            'start_time'    => '08:00:00',
            'end_time'      => '16:00:00',
            'status'        => 'active',
        ]);

        // Shift 2 (Afternoon shift: 16:00 - 00:00)
        $afternoonShift = WorkShift::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Ca Chiều (16:00 - 00:00)',
            'code'          => 'CA_CHIEU_TEST',
            'start_time'    => '16:00:00',
            'end_time'      => '00:00:00',
            'status'        => 'active',
            'is_overnight'  => true,
        ]);

        // Employee A: Leaves request
        $empA = Employee::create([
            'restaurant_id' => $restaurant->id,
            'full_name'     => 'Employee A',
            'employee_code' => 'EMPA',
            'status'        => 'active',
            'role_id'       => $role->id,
        ]);

        // Employee B: Replacement candidate 1 (will be overloaded with shifts)
        $empB = Employee::create([
            'restaurant_id' => $restaurant->id,
            'full_name'     => 'Employee B',
            'employee_code' => 'EMPB',
            'status'        => 'active',
            'role_id'       => $role->id,
        ]);

        // Employee C: Replacement candidate 2 (will have rest hours violation)
        $empC = Employee::create([
            'restaurant_id' => $restaurant->id,
            'full_name'     => 'Employee C',
            'employee_code' => 'EMPC',
            'status'        => 'active',
            'role_id'       => $role->id,
        ]);

        $targetDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addDays(6); // Sunday of current week
        $targetDateStr = $targetDate->toDateString();
        $saturdayStr = $targetDate->copy()->subDay()->toDateString();

        // Assign Employee A to the target shift on Sunday
        $assignmentA = ScheduleAssignment::create([
            'restaurant_id'  => $restaurant->id,
            'employee_id'    => $empA->id,
            'shift_id'       => $targetShift->id,
            'scheduled_date' => $targetDateStr,
            'status'         => 'scheduled',
        ]);

        // Setup Employee B: 6 shifts already this week (Monday to Saturday)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        for ($i = 0; $i < 6; $i++) {
            ScheduleAssignment::create([
                'restaurant_id'  => $restaurant->id,
                'employee_id'    => $empB->id,
                'shift_id'       => $targetShift->id,
                'scheduled_date' => $startOfWeek->copy()->addDays($i)->toDateString(),
                'status'         => 'scheduled',
            ]);
        }

        // Setup Employee C: has a shift on Saturday (yesterday) Ca Chiều (16:00 - 00:00)
        ScheduleAssignment::create([
            'restaurant_id'  => $restaurant->id,
            'employee_id'    => $empC->id,
            'shift_id'       => $afternoonShift->id,
            'scheduled_date' => $saturdayStr,
            'status'         => 'scheduled',
        ]);

        // Create LeaveRequest for Employee A on Sunday
        $leave = LeaveRequest::create([
            'restaurant_id' => $restaurant->id,
            'employee_id'   => $empA->id,
            'requested_by'  => $owner->id,
            'leave_type'    => 'annual',
            'start_date'    => $targetDateStr,
            'end_date'      => $targetDateStr,
            'status'        => 'pending',
        ]);

        // Fetch replacement suggestions
        $response = $this->actingAs($owner)
            ->get(route('employees.leaves.replacements', ['leave' => $leave->id]));

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertNotEmpty($json['assignments']);

        $assignmentData = $json['assignments'][0];
        $this->assertEquals($assignmentA->id, $assignmentData['assignment_id']);
        
        $suggestions = $assignmentData['suggestions'];
        $this->assertNotEmpty($suggestions);

        // Fetch B and C suggestions
        $sugB = collect($suggestions)->firstWhere('id', $empB->id);
        $sugC = collect($suggestions)->firstWhere('id', $empC->id);

        $this->assertNotNull($sugB);
        $this->assertNotNull($sugC);

        // Assert overtime violation for B
        $this->assertTrue($sugB['has_overtime_violation']);
        $this->assertTrue($sugB['has_warning']);
        $this->assertStringContainsString('Quá 6 ca/tuần', $sugB['warning_message']);

        // Assert rest hour violation for C
        $this->assertTrue($sugC['has_rest_violation']);
        $this->assertTrue($sugC['has_warning']);
        $this->assertStringContainsString('Thiếu nghỉ 11h', $sugC['warning_message']);
    }
}
