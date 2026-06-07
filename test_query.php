<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ScheduleAssignment;
use App\Models\WorkShift;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use App\Models\Employee;

// Mimic the test environment setup
Restaurant::where('code', 'TEST')->forceDelete();
$restaurant = Restaurant::create([
    'owner_user_id' => 1,
    'grace_period_minutes' => 10,
    'ot_multiplier' => 2.0,
    'name' => 'Test',
    'code' => 'TEST',
    'slug' => 'test',
]);

$shift = WorkShift::create([
    'restaurant_id' => $restaurant->id,
    'name' => 'Kitchen Shift',
    'code' => 'KITCHEN_SHIFT',
    'start_time' => '08:00:00',
    'end_time' => '16:00:00',
    'status' => 'active',
]);

$chef1 = Employee::create([
    'restaurant_id' => $restaurant->id,
    'role_id' => 1,
    'status' => 'active',
    'base_salary' => 10000000,
    'full_name' => 'Chef 1',
    'employee_code' => 'C1',
]);

$assign1 = ScheduleAssignment::create([
    'restaurant_id' => $restaurant->id,
    'employee_id' => $chef1->id,
    'shift_id' => $shift->id,
    'scheduled_date' => '2026-05-20',
    'status' => 'scheduled',
    'is_shift_leader' => true,
]);

echo "Assignments in DB:\n";
print_r(ScheduleAssignment::all()->toArray());

$active = ScheduleAssignment::findEmployeesOnShiftAt('2026-05-20 10:30:00', $restaurant->id);
echo "Active assignments count: " . $active->count() . "\n";
foreach ($active as $a) {
    echo "Employee ID: " . $a->employee_id . ", Is Leader: " . ($a->is_shift_leader ? 'Yes' : 'No') . "\n";
}
