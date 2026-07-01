<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\DB;
use App\Models\Restaurant;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Models\ScheduleAssignment;

// Use first restaurant or create a test one
$restaurant = Restaurant::first() ?? Restaurant::create([
    'owner_user_id' => 1,
    'grace_period_minutes' => 10,
    'ot_multiplier' => 2.0,
    'name' => 'Benchmark Rest',
    'code' => 'BENCH',
    'slug' => 'bench',
]);

$restaurantId = $restaurant->id;

// Create some dummy audit logs if there are none
if (AuditLog::where('restaurant_id', $restaurantId)->count() < 10) {
    $user = User::first() ?? User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    for ($i = 0; $i < 50; $i++) {
        AuditLog::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $user->id,
            'action' => 'price_modified',
            'event' => 'updated',
            'created_at' => now()->subHours($i),
        ]);
    }
}

// Enable query logging
DB::enableQueryLog();

$service = new FraudDetectionService();

$startTime = microtime(true);
$result = $service->getAuditLogs($restaurantId);
$endTime = microtime(true);

$queries = DB::getQueryLog();
echo "getAuditLogs executed in " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
echo "Total queries executed: " . count($queries) . "\n";
