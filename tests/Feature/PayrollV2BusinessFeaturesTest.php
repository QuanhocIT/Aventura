<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\PayslipEmailNotification;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayrollV2BusinessFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_prorated_monthly_salary_for_partial_working_days(): void
    {
        $restaurant = Restaurant::factory()->create();
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary' => 7800000, // 7.8M for easy math (300,000/day for 26 days)
            'standard_working_days' => 26,
            'salary_calculation_method' => 'standard_days',
            'allowance_meal' => 650000, // 25,000/day
            'allowance_phone' => 200000,
        ]);

        $shift = WorkShift::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        // Employee only worked 2 shifts in the month
        ScheduleAssignment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-07-01',
            'check_in_at' => '2026-07-01 08:00:00',
            'check_out_at' => '2026-07-01 17:00:00',
            'status' => 'completed',
        ]);

        ScheduleAssignment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-07-02',
            'check_in_at' => '2026-07-02 08:00:00',
            'check_out_at' => '2026-07-02 17:00:00',
            'status' => 'completed',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');

        // Base salary = (7,800,000 / 26) * 2 = 600,000
        $this->assertEquals(600000, (float) $salary->base_salary);
        $this->assertEquals(2, $salary->actual_work_days);
        $this->assertEquals(26, $salary->standard_days);

        // Meal allowance = (650,000 / 26) * 2 = 50,000. Phone = 200,000. Total allowance = 250,000.
        $this->assertEquals(250000, (float) $salary->allowance_amount);

        // Net salary = 600,000 + 250,000 = 850,000
        $this->assertEquals(850000, (float) $salary->net_salary);
    }

    public function test_night_shift_premium_calculation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'hourly',
            'pay_rate' => 30000, // 30k/h
        ]);

        // Night shift from 20:00 to 02:00 (6 hours total, 4 hours between 22:00 and 02:00)
        $shift = WorkShift::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'start_time' => '20:00:00',
            'end_time' => '02:00:00',
        ]);

        ScheduleAssignment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-07-05',
            'check_in_at' => '2026-07-05 20:00:00',
            'check_out_at' => '2026-07-06 02:00:00',
            'status' => 'completed',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');

        // Base salary = 6h * 30k = 180,000
        $this->assertEquals(180000, (float) $salary->base_salary);

        // Night shift = 4h * 30,000 * 30% = 36,000
        $this->assertEquals(36000, (float) $salary->night_shift_amount);
        $this->assertEquals(216000, (float) $salary->net_salary);
    }

    public function test_late_penalty_deduction(): void
    {
        $restaurant = Restaurant::factory()->create(['grace_period_minutes' => 5]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary' => 8000000,
            'salary_calculation_method' => 'fixed_package', // Full package
        ]);

        $shift = WorkShift::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        // Checked in 35 minutes late (08:35:00 vs 08:00:00, grace 5m -> 30m late -> Tier 30-60m: 50,000đ)
        ScheduleAssignment::factory()->create([
            'restaurant_id' => $restaurant->id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'scheduled_date' => '2026-07-10',
            'check_in_at' => '2026-07-10 08:35:00',
            'check_out_at' => '2026-07-10 17:00:00',
            'status' => 'completed',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');

        $this->assertEquals(50000, (float) $salary->late_penalty_amount);
        $this->assertEquals(7950000, (float) $salary->net_salary);
    }

    public function test_bank_export_csv_download(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $owner->id,
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'NGUYEN VAN A',
            'base_salary' => 10000000,
            'salary_calculation_method' => 'fixed_package',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');
        $salary->update(['status' => 'approved']);

        $this->actingAs($owner);

        $response = $this->get(route('salaries.export-bank', [
            'period' => '2026-07',
            'bank_format' => 'vcb',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('1234567890', $response->streamedContent());
        $this->assertStringContainsString('NGUYEN VAN A', $response->streamedContent());
    }

    public function test_send_payslip_notifications(): void
    {
        Notification::fake();

        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'email' => 'employee@example.com',
            'name' => 'Tran Van B',
        ]);
        $employee = Employee::factory()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'base_salary' => 9000000,
            'salary_calculation_method' => 'fixed_package',
        ]);

        $salaryService = app(SalaryService::class);
        $salary = $salaryService->getOrCreateDraft($restaurant->id, $employee, '2026-07-01');
        $salary->update(['status' => 'approved']);

        $this->actingAs($owner);

        $response = $this->post(route('salaries.send-payslips'), [
            'salary_ids' => [$salary->id],
        ]);

        $response->assertRedirect();
        $salary->refresh();
        $this->assertNotNull($salary->email_sent_at);

        Notification::assertSentTo(
            $user,
            PayslipEmailNotification::class,
            function ($notification) use ($salary) {
                return $notification->salary->id === $salary->id;
            }
        );
    }
}
