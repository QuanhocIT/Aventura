<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emails = [
    'cashier_vq@aventura.local',
    'manager_vq@aventura.local',
    'kitchen_vq@aventura.local',
    'inventory_vq@aventura.local',
];

$hashedPassword = \Illuminate\Support\Facades\Hash::make('12345678');

foreach ($emails as $email) {
    $user = \App\Models\User::where('email', $email)->first();
    if ($user) {
        $user->update(['password' => $hashedPassword]);
        echo "Successfully reset password to '12345678' for user: {$email}\n";
    } else {
        echo "User not found for email: {$email}\n";
    }
}
