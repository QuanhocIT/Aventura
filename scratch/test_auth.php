<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = new \Illuminate\Http\Request();
$request->merge([
    'email' => 'cashier.enterprise@test.com',
    'password' => 'password'
]);

try {
    $users = \App\Models\User::where('email', $request->email)->get();
    echo "Found " . $users->count() . " users with this email.\n";

    $matchedUsers = $users->filter(function ($u) use ($request) {
        $check = \Illuminate\Support\Facades\Hash::check($request->password, $u->password);
        echo "User ID {$u->id}: password check = " . ($check ? "TRUE" : "FALSE") . "\n";
        return $check;
    });

    if ($matchedUsers->isEmpty()) {
        echo "Result: matchedUsers is empty -> returns null (These credentials do not match our records)\n";
        exit;
    }

    $activeUsers = $matchedUsers->filter(fn($u) => $u->status === 'active');
    if ($activeUsers->isEmpty()) {
        echo "Result: activeUsers is empty -> throws ValidationException (Tài khoản của bạn đã bị khóa...)\n";
        exit;
    }

    echo "Active users count: " . $activeUsers->count() . "\n";

    if ($activeUsers->count() === 1) {
        $user = $activeUsers->first();
        echo "Checking shift lock for single user. Restaurant ID: {$user->restaurant_id}, isSuperAdmin: " . ($user->isSuperAdmin() ? "YES" : "NO") . ", roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";
        
        if ($user->restaurant_id && !$user->isSuperAdmin() && !$user->hasAnyRole(['owner', 'manager', 'supplier'])) {
            $employee = $user->employee;
            if (!$employee) {
                echo "No employee profile found!\n";
            } else {
                echo "Employee profile: {$employee->full_name}\n";
            }
            if (!$employee || !$employee->isWithinScheduledShift()) {
                echo "Result: isWithinScheduledShift is FALSE -> throws ValidationException (Tài khoản của bạn chỉ được phép truy cập trong khung giờ...)\n";
                exit;
            }
        }
        echo "Result: Success, returns user.\n";
    }
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Caught ValidationException: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE) . "\n";
}
