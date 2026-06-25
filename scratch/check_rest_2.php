<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$r = \App\Models\Restaurant::find(2);
if ($r) {
    echo "Restaurant ID 2 found!\n";
    echo "  Name: " . $r->name . "\n";
    echo "  Branches count: " . $r->branches()->count() . "\n";
    echo "  Tables count: " . $r->tables()->count() . "\n";
    echo "  Employees count: " . $r->employees()->count() . "\n";
    echo "  Products count: " . $r->products()->count() . "\n";
} else {
    echo "Restaurant ID 2 NOT found!\n";
}
