<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========== DETAILED DATA COMPARISON ==========\n\n";

$dataTablesInSQL = [
    'migrations' => 24,
    'permissions' => 15,
    'pulse_aggregates' => 8,
    'pulse_entries' => 1,
    'role_has_permissions' => 52,
    'roles' => 7,
    'subscription_plans' => 2,
];

$results = [];
$allMatch = true;

foreach ($dataTablesInSQL as $table => $expectedCount) {
    $actualCount = DB::table($table)->count();
    $match = ($actualCount === $expectedCount);
    $results[$table] = [
        'expected' => $expectedCount,
        'actual' => $actualCount,
        'match' => $match
    ];
    if (!$match) {
        $allMatch = false;
    }
}

echo "Table Comparison:\n";
echo str_repeat("-", 70) . "\n";
printf("%-35s | %-12s | %-12s | %s\n", "Table", "Expected", "Actual", "Status");
echo str_repeat("-", 70) . "\n";

foreach ($results as $table => $data) {
    $status = $data['match'] ? '✅ MATCH' : '❌ DIFFER';
    printf("%-35s | %-12d | %-12d | %s\n", $table, $data['expected'], $data['actual'], $status);
}

echo str_repeat("-", 70) . "\n\n";

if ($allMatch) {
    echo "✅ RESULT: All data tables match perfectly!\n";
    echo "Your database is synchronized with aventura.sql\n";
} else {
    echo "❌ RESULT: Some tables have data mismatch!\n";
    echo "Differences found in:\n";
    foreach ($results as $table => $data) {
        if (!$data['match']) {
            echo "  - $table: Expected {$data['expected']}, but got {$data['actual']}\n";
        }
    }
}

echo "\n========== ADDITIONAL INFO ==========\n";
echo "Note: Tables without data (41 empty tables) are expected if only\n";
echo "system setup data is imported. User/business data should be added\n";
echo "through the application or separate seed files.\n";
