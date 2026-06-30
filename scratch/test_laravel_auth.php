<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;

// Simulate a post request to /login
$credentials = [
    'email' => 'cashier.enterprise@test.com',
    'password' => 'password',
];

try {
    $success = Auth::attempt($credentials);
    echo "Auth::attempt returned: " . ($success ? "TRUE" : "FALSE") . "\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Auth::attempt threw ValidationException: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Exception $e) {
    echo "Auth::attempt threw Exception: " . $e->getMessage() . "\n";
}
