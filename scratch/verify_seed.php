<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$id = \App\Models\User::where('email', 'owner@bepso.test')->value('restaurant_id');
echo "restaurant_id=$id\n";
echo "Shifts: "          . \App\Models\WorkShift::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Ingredients: "     . \App\Models\Ingredient::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Products: "        . \App\Models\Product::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Recipes: "         . \App\Models\ProductRecipe::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Tables: "          . \App\Models\RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Orders completed: ". \App\Models\Order::withoutGlobalScopes()->where('restaurant_id', $id)->where('status', 'completed')->count() . "\n";
echo "Orders pending: "  . \App\Models\Order::withoutGlobalScopes()->where('restaurant_id', $id)->whereIn('status', ['pending', 'confirmed'])->count() . "\n";
echo "InvTransactions: " . \App\Models\InventoryTransaction::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "ShiftClosings: "   . \App\Models\ShiftClosing::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Salaries: "        . \App\Models\Salary::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "SalAdj: "          . \App\Models\SalaryAdjustment::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Customers: "       . \App\Models\Customer::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Promotions: "      . \App\Models\Promotion::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Feedback: "        . \App\Models\CustomerFeedback::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Violations: "      . \App\Models\ViolationReport::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "Approvals: "       . \App\Models\ApprovalRequest::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";
echo "LeaveReqs: "       . \App\Models\LeaveRequest::withoutGlobalScopes()->where('restaurant_id', $id)->count() . "\n";

// Kiểm tra salary adjustments chi tiết
$adjs = \App\Models\SalaryAdjustment::withoutGlobalScopes()->where('restaurant_id', $id)->get();
echo "\n--- Salary Adjustments ---\n";
foreach ($adjs as $a) {
    echo "  [{$a->type}] {$a->amount} — {$a->reason}\n";
}

// Kiểm tra net_salary EMP-003
$emp3 = \App\Models\Employee::withoutGlobalScopes()->where('restaurant_id', $id)->where('employee_code', 'EMP-003')->first();
$sal3 = \App\Models\Salary::withoutGlobalScopes()->where('restaurant_id', $id)->where('employee_id', $emp3->id)->first();
if ($sal3) {
    echo "\nEMP-003 salary: base={$sal3->base_salary} deductions={$sal3->deduction_amount} net={$sal3->net_salary}\n";
}
