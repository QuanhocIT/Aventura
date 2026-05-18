<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "========== DATABASE DATA VERIFICATION ==========\n\n";

// Get all tables
$tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'aventura'");

$tableNames = array_map(fn($t) => $t->TABLE_NAME, $tables);
sort($tableNames);

$summary = [];
$hasData = false;

foreach ($tableNames as $table) {
    try {
        $count = DB::table($table)->count();
        $summary[$table] = $count;
        if ($count > 0) {
            $hasData = true;
        }
    } catch (\Exception $e) {
        $summary[$table] = "ERROR";
    }
}

echo "Total tables: " . count($summary) . "\n\n";

echo "Tables with data:\n";
$dataCount = 0;
foreach ($summary as $table => $count) {
    if (is_numeric($count) && $count > 0) {
        echo "  - $table: $count records\n";
        $dataCount++;
    }
}

if ($dataCount === 0) {
    echo "  [NONE - All tables are empty]\n";
}

echo "\n\nTables without data (empty):\n";
$emptyCount = 0;
foreach ($summary as $table => $count) {
    if (is_numeric($count) && $count === 0) {
        echo "  - $table\n";
        $emptyCount++;
    }
}

echo "\n========== SUMMARY ==========\n";
echo "Total tables: " . count($summary) . "\n";
echo "Tables with data: $dataCount\n";
echo "Empty tables: $emptyCount\n";

if ($dataCount === 0) {
    echo "\n⚠️  WARNING: All tables are empty!\n";
    echo "The database appears to be freshly migrated without seed data.\n";
} else {
    echo "\n✅ Database has data in $dataCount table(s)\n";
}
