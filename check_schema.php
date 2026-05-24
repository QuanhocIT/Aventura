<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = DB::select("
    SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'aventura' AND TABLE_NAME = 'audit_logs'
    ORDER BY ORDINAL_POSITION
");

echo "=== Current audit_logs schema in database ===\n";
foreach ($columns as $col) {
    echo sprintf("%-20s | %-40s | %s | %s\n",
        $col->COLUMN_NAME,
        $col->COLUMN_TYPE,
        $col->IS_NULLABLE,
        $col->COLUMN_DEFAULT ?? 'NULL'
    );
}
