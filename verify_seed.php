<?php

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\Promotion;
use App\Models\RestaurantTable;
use App\Models\Salary;
use App\Models\SalaryAdjustment;
use App\Models\ShiftClosing;
use App\Models\User;
use App\Models\ViolationReport;
use App\Models\WorkShift;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$id = User::where('email', 'owner@bepso.test')->value('restaurant_id');
echo "restaurant_id=$id\n";
echo 'Shifts: '.WorkShift::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Ingredients: '.Ingredient::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Products: '.Product::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Recipes: '.ProductRecipe::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Tables: '.RestaurantTable::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Orders completed: '.Order::withoutGlobalScopes()->where('restaurant_id', $id)->where('status', 'completed')->count()."\n";
echo 'Orders pending: '.Order::withoutGlobalScopes()->where('restaurant_id', $id)->whereIn('status', ['pending', 'confirmed'])->count()."\n";
echo 'InvTransactions: '.InventoryTransaction::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'ShiftClosings: '.ShiftClosing::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Salaries: '.Salary::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'SalAdj: '.SalaryAdjustment::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Customers: '.Customer::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Promotions: '.Promotion::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Feedback: '.CustomerFeedback::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Violations: '.ViolationReport::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'Approvals: '.ApprovalRequest::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";
echo 'LeaveReqs: '.LeaveRequest::withoutGlobalScopes()->where('restaurant_id', $id)->count()."\n";

// Kiểm tra salary adjustments chi tiết
$adjs = SalaryAdjustment::withoutGlobalScopes()->where('restaurant_id', $id)->get();
echo "\n--- Salary Adjustments ---\n";
foreach ($adjs as $a) {
    echo "  [{$a->type}] {$a->amount} — {$a->reason}\n";
}

// Kiểm tra net_salary EMP-003
$emp3 = Employee::withoutGlobalScopes()->where('restaurant_id', $id)->where('employee_code', 'EMP-003')->first();
$sal3 = Salary::withoutGlobalScopes()->where('restaurant_id', $id)->where('employee_id', $emp3->id)->first();
if ($sal3) {
    echo "\nEMP-003 salary: base={$sal3->base_salary} deductions={$sal3->deduction_amount} net={$sal3->net_salary}\n";
}
