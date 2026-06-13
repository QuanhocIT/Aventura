<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SearchQueryLog;
use App\Jobs\SyncSearchIndex;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Meilisearch\Client;

class MeilisearchConsoleController extends Controller
{
    protected array $indexesConfig = [
        [
            'index_name' => 'restaurants',
            'label' => 'Nhà hàng',
            'model' => \App\Models\Restaurant::class,
        ],
        [
            'index_name' => 'products',
            'label' => 'Thực đơn & Món ăn',
            'model' => \App\Models\Product::class,
        ],
        [
            'index_name' => 'orders',
            'label' => 'Đơn hàng',
            'model' => \App\Models\Order::class,
        ],
    ];

    public function index(Request $request): Response
    {
        $host = config('scout.meilisearch.host', 'http://localhost:7700');
        $driver = config('scout.driver', 'collection');
        $online = false;
        $databaseSize = 0;
        $error = null;
        $meiliStats = [];

        try {
            $client = new Client($host, config('scout.meilisearch.key'));
            $health = $client->health();
            if (isset($health['status']) && $health['status'] === 'available') {
                $online = true;
                $meiliStats = $client->stats();
                $databaseSize = $meiliStats['databaseSize'] ?? 0;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $indexes = [];
        foreach ($this->indexesConfig as $cfg) {
            $indexName = $cfg['index_name'];
            $modelClass = $cfg['model'];

            // Get docs count from Meilisearch
            $documentsCount = 0;
            $isIndexing = false;
            if ($online && isset($meiliStats['indexes'][$indexName])) {
                $documentsCount = $meiliStats['indexes'][$indexName]['numberOfDocuments'] ?? 0;
                $isIndexing = $meiliStats['indexes'][$indexName]['isIndexing'] ?? false;
            }

            // Get db count
            $dbRecordsCount = 0;
            try {
                $dbRecordsCount = $modelClass::count();
            } catch (\Throwable $e) {
                // Ignore DB table errors
            }

            // Sync Status from cache
            $syncStatus = Cache::get('meilisearch_sync_' . $indexName) ?? [
                'status' => 'idle',
                'action' => null,
                'completed_at' => null,
                'failed_at' => null,
                'error' => null,
            ];

            $indexes[] = [
                'index_name' => $indexName,
                'label' => $cfg['label'],
                'model' => $modelClass,
                'documents_count' => $documentsCount,
                'db_records_count' => $dbRecordsCount,
                'is_indexing' => $isIndexing,
                'sync_status' => $syncStatus,
                'out_of_sync' => $documentsCount !== $dbRecordsCount,
            ];
        }

        // Statistics
        try {
            $totalSearches = SearchQueryLog::count();
            $averageLatency = round(SearchQueryLog::avg('latency_ms') ?? 0, 2);

            $latencyByIndex = SearchQueryLog::select('index_name')
                ->selectRaw('ROUND(AVG(latency_ms), 2) as avg_latency')
                ->selectRaw('COUNT(*) as total_count')
                ->groupBy('index_name')
                ->get()
                ->map(fn ($item) => [
                    'index_name' => $item->index_name,
                    'avg_latency' => (float) $item->avg_latency,
                    'total_count' => (int) $item->total_count,
                ])
                ->toArray();

            $topKeywords = SearchQueryLog::select('keyword', 'index_name')
                ->selectRaw('COUNT(*) as search_count')
                ->selectRaw('ROUND(AVG(latency_ms), 2) as avg_latency')
                ->groupBy('keyword', 'index_name')
                ->orderByDesc('search_count')
                ->limit(10)
                ->get()
                ->map(fn ($item, $index) => [
                    'rank' => $index + 1,
                    'keyword' => $item->keyword,
                    'index_name' => $item->index_name,
                    'search_count' => (int) $item->search_count,
                    'avg_latency' => (float) $item->avg_latency,
                ])
                ->toArray();

            $latestSearches = SearchQueryLog::latest()
                ->limit(20)
                ->get()
                ->map(fn ($item) => [
                    'keyword' => $item->keyword,
                    'index_name' => $item->index_name,
                    'latency_ms' => (float) $item->latency_ms,
                    'created_at' => $item->created_at->format('d/m/Y H:i:s'),
                ])
                ->toArray();
        } catch (\Throwable $e) {
            // If DB schema doesn't exist yet, return dummy values
            $totalSearches = 0;
            $averageLatency = 0.0;
            $latencyByIndex = [];
            $topKeywords = [];
            $latestSearches = [];
        }

        return Inertia::render('super-admin/meilisearch-console/Index', [
            'connection' => [
                'online' => $online,
                'host' => $host,
                'driver' => $driver,
                'database_size' => $databaseSize,
                'error' => $error,
            ],
            'indexes' => $indexes,
            'statistics' => [
                'total_searches' => $totalSearches,
                'average_latency' => $averageLatency,
                'latency_by_index' => $latencyByIndex,
                'top_keywords' => $topKeywords,
                'latest_searches' => $latestSearches,
            ],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'index_name' => 'required|string',
            'action' => 'required|in:import,flush',
        ]);

        $cfg = collect($this->indexesConfig)->firstWhere('index_name', $validated['index_name']);
        if (!$cfg) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ mục không hợp lệ hoặc chưa được đăng ký trong hệ thống.',
            ], 400);
        }

        $cacheKey = 'meilisearch_sync_' . $validated['index_name'];
        $syncStatus = [
            'status' => 'pending',
            'action' => $validated['action'],
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
        ];
        Cache::put($cacheKey, $syncStatus);

        // Dispatch background job
        SyncSearchIndex::dispatch($validated['action'], $cfg['model'], $validated['index_name']);

        return response()->json([
            'success' => true,
            'sync_status' => $syncStatus,
            'message' => 'Yêu cầu ' . ($validated['action'] === 'import' ? 'Đồng bộ' : 'Xóa sạch') . ' chỉ mục đã được đưa vào hàng đợi xử lý.',
        ]);
    }

    public function clearStats(Request $request): JsonResponse
    {
        try {
            SearchQueryLog::truncate();
            return response()->json(['success' => true, 'message' => 'Đã xóa sạch lịch sử thống kê truy vấn.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi xóa dữ liệu: ' . $e->getMessage()], 500);
        }
    }
}
