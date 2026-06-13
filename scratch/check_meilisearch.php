<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Scout Driver: " . config('scout.driver') . "\n";
echo "Meilisearch Host: " . config('scout.meilisearch.host') . "\n";
echo "Meilisearch Key: " . config('scout.meilisearch.key') . "\n";

try {
    $client = new \Meilisearch\Client(
        config('scout.meilisearch.host', 'http://localhost:7700'),
        config('scout.meilisearch.key')
    );
    $health = $client->health();
    echo "Meilisearch Health: " . json_encode($health) . "\n";
    
    $stats = $client->stats();
    echo "Meilisearch Stats: " . json_encode($stats) . "\n";
} catch (\Throwable $e) {
    echo "Error connecting to Meilisearch: " . $e->getMessage() . "\n";
}
