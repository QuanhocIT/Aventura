<?php
// Quick Redis connection check
try {
    require dirname(__DIR__) . '/vendor/autoload.php';
    $app = require_once dirname(__DIR__) . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $result = \Illuminate\Support\Facades\Redis::connection()->ping();
    echo "REDIS OK: " . ($result ? 'true' : 'false') . "\n";
} catch (\Throwable $e) {
    echo "REDIS ERROR: " . $e->getMessage() . "\n";
}
