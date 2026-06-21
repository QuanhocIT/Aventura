<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Restaurants and Subscriptions ===\n";
$restaurants = \App\Models\Restaurant::with(['subscriptions.plan'])->get();

foreach ($restaurants as $restaurant) {
    echo "Restaurant ID: {$restaurant->id}\n";
    echo "Name: {$restaurant->name}\n";
    echo "Domain: {$restaurant->domain}\n";
    echo "Status: {$restaurant->status}\n";
    
    echo "Subscriptions:\n";
    if ($restaurant->subscriptions->isEmpty()) {
        echo "  No subscriptions found.\n";
    } else {
        foreach ($restaurant->subscriptions as $sub) {
            echo "  - Sub ID: {$sub->id}\n";
            echo "    Plan: " . ($sub->plan?->name ?? 'N/A') . " (ID: {$sub->plan_id})\n";
            echo "    Status: {$sub->status}\n";
            echo "    Price: " . number_format($sub->price ?? 0) . " VND\n";
            echo "    Original Price: " . number_format($sub->original_price ?? 0) . " VND\n";
            echo "    Starts At: {$sub->starts_at}\n";
            echo "    Ends At: {$sub->ends_at}\n";
            echo "    Renewal At: " . ($sub->renewal_at ?? 'N/A') . "\n";
            echo "    Auto Renewal: " . ($sub->auto_renewal ? 'Yes' : 'No') . "\n";
            echo "    Transaction Code: " . ($sub->transaction_code ?? 'N/A') . "\n";
        }
    }
    echo "-------------------------------------\n";
}

echo "=== All Billing Invoices ===\n";
$invoices = \App\Models\BillingInvoice::all();
foreach ($invoices as $invoice) {
    echo "Invoice ID: {$invoice->id}\n";
    echo "  Restaurant ID: {$invoice->restaurant_id}\n";
    echo "  Amount: " . number_format($invoice->amount) . " VND\n";
    echo "  Status: {$invoice->status}\n";
    echo "  Payment Method: {$invoice->payment_method}\n";
    echo "  Paid At: {$invoice->paid_at}\n";
    echo "  Transaction Reference: {$invoice->transaction_reference}\n";
}
