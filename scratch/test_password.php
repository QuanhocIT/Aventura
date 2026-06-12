<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'cashier_vq@aventura.local')->first();
if ($user) {
    $passwords = ['password', '123456', '12345678', '123456789', 'admin', 'admin123', 'cashier123', 'cashier'];
    echo "Checking passwords for {$user->email}:\n";
    foreach ($passwords as $p) {
        $check = \Illuminate\Support\Facades\Hash::check($p, $user->password);
        echo "Password: '{$p}' -> " . ($check ? "MATCH ✅" : "NO MATCH ❌") . "\n";
    }
} else {
    echo "User not found\n";
}
