<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Schedule Assignments for Today (2026-06-03) ---\n";
$today = '2026-06-03';
$assignments = \App\Models\ScheduleAssignment::whereDate('scheduled_date', $today)
    ->with(['employee.user', 'shift'])
    ->get();

foreach ($assignments as $sa) {
    $emp = $sa->employee;
    $user = $emp?->user;
    $shift = $sa->shift;
    echo "Employee: " . ($emp?->full_name ?? 'N/A') . "\n";
    echo "  Email: " . ($user?->email ?? 'N/A') . "\n";
    echo "  Shift: " . ($shift?->name ?? 'N/A') . " (" . ($shift?->start_time ?? 'N/A') . " - " . ($shift?->end_time ?? 'N/A') . ")\n";
    echo "  Status: " . $sa->status . "\n";
}

echo "\n--- Inspecting User Account cashier_vq@aventura.local ---\n";
$targetUser = \App\Models\User::where('email', 'cashier_vq@aventura.local')->first();
if ($targetUser) {
    echo "User found!\n";
    echo "  ID: {$targetUser->id}\n";
    echo "  Name: {$targetUser->name}\n";
    echo "  Email: {$targetUser->email}\n";
    echo "  Status: " . ($targetUser->status ?? 'N/A') . "\n";
    echo "  Email Verified At: " . ($targetUser->email_verified_at ?? 'N/A') . "\n";
    echo "  Password (hashed): {$targetUser->password}\n";
    
    // Check role
    $roles = $targetUser->roles ?? [];
    echo "  Roles: " . json_encode($roles) . "\n";
    
    // Check associated employee
    $emp = \App\Models\Employee::where('user_id', $targetUser->id)->first();
    if ($emp) {
        echo "  Employee: {$emp->full_name} (Status: {$emp->status})\n";
    } else {
        echo "  No associated Employee found!\n";
    }
} else {
    echo "User cashier_vq@aventura.local NOT found!\n";
}

echo "\n--- Inspecting all users in DB ---\n";
$users = \App\Models\User::all();
foreach ($users as $u) {
    echo "ID: {$u->id}, Name: {$u->name}, Email: {$u->email}, Status: " . ($u->status ?? 'N/A') . "\n";
}
