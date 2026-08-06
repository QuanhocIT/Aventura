<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TemporaryOrder;

echo "--- RESTAURANTS ---\n";
$restaurants = Restaurant::all(['id', 'name']);
foreach ($restaurants as $r) {
    echo "ID {$r->id}: {$r->name}\n";
}

echo "\n--- USERS / EMPLOYEES --- \n";
$users = User::with('restaurant')->get(['id', 'name', 'email', 'restaurant_id']);
foreach ($users as $u) {
    echo "User ID {$u->id}: {$u->name} ({$u->email}) => Restaurant ID {$u->restaurant_id}: " . ($u->restaurant?->name ?? 'N/A') . "\n";
}

echo "\n--- TABLE 19 / B1 --- \n";
$table = RestaurantTable::withoutGlobalScopes()->where('qr_token', 'KACvg2dNDyNTnJOhQG4WT6Xi92YJyi2x')->first();
if ($table) {
    echo "Table ID {$table->id}: {$table->name}, Restaurant ID: {$table->restaurant_id}\n";
} else {
    echo "Table not found by token.\n";
}

echo "\n--- TEMPORARY ORDERS ---\n";
$tempOrders = TemporaryOrder::withoutGlobalScopes()->latest()->take(5)->get();
foreach ($tempOrders as $to) {
    echo "TempOrder ID {$to->id}: Restaurant ID {$to->restaurant_id}, Table ID {$to->table_id}, Status: {$to->status}\n";
}
