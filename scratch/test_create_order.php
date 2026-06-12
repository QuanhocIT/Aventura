<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = \App\Models\Order::create([
    'restaurant_id' => 1,
    'branch_id' => 1,
    'table_id' => 1,
    'created_by' => 3,
    'order_number' => 'ORD-TEST-REALTIME-' . strtoupper(uniqid()),
    'channel' => 'dine_in',
    'status' => 'pending',
    'payment_status' => 'unpaid',
    'subtotal' => 100000,
    'total_amount' => 100000,
]);

echo "Order created successfully!\n";
echo "Laravel time now: " . now()->toDateTimeString() . "\n";
echo "Order created_at: " . $order->created_at->toDateTimeString() . "\n";

// Query from DB again to verify persistence timezone conversion
$fetched = \App\Models\Order::find($order->id);
echo "Fetched from DB created_at: " . $fetched->created_at->toDateTimeString() . "\n";

// Clean up
$order->delete();
echo "Cleaned up test order.\n";
