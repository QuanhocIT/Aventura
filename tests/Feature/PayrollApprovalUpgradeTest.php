<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Restaurant;
use App\Models\Salary;
use App\Models\User;
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
            'restaurant_id'     => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary'        => 8000000,
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

    public function test_owner_can_update_employee_compensation_type_and_rates(): void
    {
        $restaurant = Restaurant::factory()->create();
        $owner = User::factory()->create(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        $employee = Employee::factory()->create([
            'restaurant_id'     => $restaurant->id,
            'compensation_type' => 'fixed',
            'base_salary'       => 6000000,
        ]);

        $this->actingAs($owner);

        $response = $this->patch(route('employees.update', $employee->id), [
            'compensation_type' => 'hourly',
            'pay_rate'          => 35000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'id'                => $employee->id,
            'compensation_type' => 'hourly',
            'pay_rate'          => 35000,
        ]);
    }
}
