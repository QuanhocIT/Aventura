<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Restaurant;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApprovalUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_salary_breakdown_formula_for_fixed_monthly_salary(): void
    {
        $restaurant = Restaurant::factory()->create();
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary' => 8000000,
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');

        $breakdown = $salaryService->getSalaryCalculationDetails($salary);

        $this->assertEquals('fixed', $breakdown['compensation_type']);
        $this->assertEquals('Lương tháng cố định', $breakdown['compensation_type_label']);
        $this->assertEquals(8000000, $breakdown['contract_salary']);
        $this->assertStringContainsString('8,000,000', $breakdown['formula_text']);
        $this->assertStringContainsString('26 ngày chuẩn', $breakdown['formula_text']);
    }

    public function test_bulk_approve_salaries(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $emp1 = Employee::factory()->create(['restaurant_id' => $restaurant->id, 'user_id' => $owner->id]);
        $emp2 = Employee::factory()->create(['restaurant_id' => $restaurant->id]);

        $salaryService = app(SalaryService::class);
        $sal1 = $salaryService->getOrCreateDraft($restaurant->id, $emp1, '2026-07-01');
        $sal2 = $salaryService->getOrCreateDraft($restaurant->id, $emp2, '2026-07-01');

        $this->actingAs($owner);

        $response = $this->post(route('salaries.approve-bulk'), [
            'salary_ids' => [$sal1->id, $sal2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('salaries', ['id' => $sal1->id, 'status' => 'approved']);
        $this->assertDatabaseHas('salaries', ['id' => $sal2->id, 'status' => 'approved']);
    }

    public function test_regeneration_does_not_mutate_approved_or_paid_salary(): void
    {
        $restaurant = Restaurant::factory()->create();
        $approvedEmployee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'base_salary' => 6000000,
        ]);
        $paidEmployee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'base_salary' => 7000000,
        ]);

        $salaryService = app(SalaryService::class);
        $approvedSalary = $salaryService->getOrCreateDraft($restaurant->id, $approvedEmployee, '2026-07-01');
        $paidSalary = $salaryService->getOrCreateDraft($restaurant->id, $paidEmployee, '2026-07-01');
        $approvedSalary->update([
            'status' => 'approved',
            'base_salary' => 1234567,
            'net_salary' => 1234567,
        ]);
        $paidSalary->update([
            'status' => 'paid',
            'base_salary' => 2345678,
            'net_salary' => 2345678,
        ]);

        $result = $salaryService->generateMonthlyDrafts($restaurant->id, '2026-07');

        $this->assertSame(2, $result['locked']);
        $this->assertDatabaseHas('salaries', [
            'id' => $approvedSalary->id,
            'status' => 'approved',
            'base_salary' => 1234567,
            'net_salary' => 1234567,
        ]);
        $this->assertDatabaseHas('salaries', [
            'id' => $paidSalary->id,
            'status' => 'paid',
            'base_salary' => 2345678,
            'net_salary' => 2345678,
        ]);
    }

    public function test_hourly_breakdown_matches_payroll_and_counts_leave_overlap(): void
    {
        $restaurant = Restaurant::factory()->create(['ot_multiplier' => 1.5]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'hourly',
            'pay_rate' => 50000,
        ]);
        $shift = WorkShift::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);
        ScheduleAssignment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-07-10',
            'check_in_at' => '2026-07-10 08:00:00',
            'check_out_at' => '2026-07-10 18:00:00',
            'status' => 'completed',
        ]);
        OvertimeRequest::create([
            'restaurant_id' => $restaurant->id,
            'employee_id' => $employee->id,
            'scheduled_date' => '2026-07-10',
            'hours_requested' => 2,
            'hours_approved' => 2,
            'status' => 'approved',
        ]);
        LeaveRequest::create([
            'restaurant_id' => $restaurant->id,
            'employee_id' => $employee->id,
            'leave_type' => 'unpaid',
            'start_date' => '2026-06-30',
            'end_date' => '2026-07-02',
            'status' => 'approved',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');
        $breakdown = $salaryService->getSalaryCalculationDetails($salary);

        $this->assertSame(550000.0, (float) $salary->base_salary);
        $this->assertSame(8.0, (float) $breakdown['regular_hours']);
        $this->assertSame(2.0, (float) $breakdown['ot_hours']);
        $this->assertEquals(2, $breakdown['unpaid_leave_days']);
        $this->assertStringContainsString('8.00h', $breakdown['formula_text']);
        $this->assertStringContainsString('2.00h OT', $breakdown['formula_text']);
    }

    public function test_owner_can_update_employee_compensation_type_and_rates(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary' => 6000000,
        ]);

        $this->actingAs($owner);

        $response = $this->patch(route('employees.update', $employee->id), [
            'compensation_type' => 'hourly',
            'pay_rate' => 35000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'compensation_type' => 'hourly',
            'pay_rate' => 35000,
        ]);
    }
}
