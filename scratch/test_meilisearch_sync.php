<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SearchQueryLog;
use App\Models\Customer;
use App\Jobs\SyncSearchIndex;
use Illuminate\Support\Facades\Cache;

echo "--- Meilisearch Sync and Stats Verification Script ---\n";

// 1. Clean previous sync cache
echo "Cleaning cache...\n";
Cache::forget('meilisearch_sync_customers');

// 2. Dispatch the Sync job synchronously for test
echo "Dispatching SyncSearchIndex job for customers...\n";
$job = new SyncSearchIndex('import', Customer::class, 'customers');
$job->handle();

// Check Cache results
$status = Cache::get('meilisearch_sync_customers');
echo "Sync Job Status in Cache: " . json_encode($status) . "\n";

// 3. Create a test search query log
echo "Creating a test search query log...\n";
$log = SearchQueryLog::create([
    'keyword' => 'John Doe',
    'index_name' => 'customers',
    'latency_ms' => 12.34,
    'restaurant_id' => 1,
    'user_id' => 1,
]);

echo "Log created: " . json_encode($log) . "\n";

// Retrieve top keywords stats
$topKeywords = SearchQueryLog::select('keyword', 'index_name', \Illuminate\Support\Facades\DB::raw('COUNT(*) as search_count'), \Illuminate\Support\Facades\DB::raw('AVG(latency_ms) as avg_latency'))
    ->groupBy('keyword', 'index_name')
    ->get()
    ->toArray();

echo "Top Keywords Stats: " . json_encode($topKeywords) . "\n";

// Clean up test log
echo "Cleaning up test log...\n";
$log->delete();

echo "Verification completed successfully!\n";
