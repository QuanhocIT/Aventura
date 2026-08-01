<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\DeliverWebhookJob;
use App\Models\MaintenanceMode;
use App\Models\Restaurant;
use App\Models\ServiceMaintenanceStatus;
use App\Models\WebhookDelivery;
use App\Services\CircuitBreaker;
use App\Services\ServiceMonitorService;
use App\Services\SuperAdminAuditStream;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class OperationsCenterController extends Controller
{
    public function __construct(private ServiceMonitorService $monitorService) {}

    public function index(): Response
    {
        return Inertia::render('super-admin/operations/Index', $this->snapshot());
    }

    public function refresh(): JsonResponse
    {
        return response()->json(['success' => true] + $this->snapshot());
    }

    public function retryFailedJob(Request $request, string $uuid): JsonResponse
    {
        abort_unless(Schema::hasTable('failed_jobs'), 404);

        $failedJob = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($failedJob, 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        SuperAdminAuditStream::record('retry_failed_job', [
            'uuid' => $uuid,
            'queue' => $failedJob->queue,
            'before' => ['failed' => true],
            'after' => ['queued' => true],
        ], null, null, 'warning');

        return response()->json(['success' => true, 'message' => 'Đã đưa failed job trở lại queue.']);
    }

    public function retryWebhook(Request $request, int $delivery): JsonResponse
    {
        $webhook = WebhookDelivery::with('endpoint')->findOrFail($delivery);
        $before = ['status' => $webhook->status, 'attempts' => $webhook->attempts];

        $webhook->update([
            'status' => 'pending',
            'response_code' => null,
            'response_body' => null,
            'delivered_at' => null,
        ]);
        DeliverWebhookJob::dispatch($webhook->id);

        SuperAdminAuditStream::record('retry_webhook_delivery', [
            'delivery_id' => $webhook->id,
            'endpoint_id' => $webhook->webhook_endpoint_id,
            'before' => $before,
            'after' => ['status' => 'pending', 'queued' => true],
        ], WebhookDelivery::class, $webhook->id, 'warning');

        return response()->json(['success' => true, 'message' => 'Đã đưa webhook delivery trở lại queue.']);
    }

    public function updateMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:global,tenant'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'enabled' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        if ($data['scope'] === 'global') {
            $data['restaurant_id'] = null;
        } elseif (! $data['restaurant_id']) {
            abort(422, 'Tenant maintenance cần restaurant_id.');
        }

        $mode = MaintenanceMode::query()
            ->where($data['restaurant_id'] === null ? [['restaurant_id', '=', null]] : ['restaurant_id', $data['restaurant_id']])
            ->first();

        $before = $mode?->only(['restaurant_id', 'enabled', 'message', 'starts_at', 'ends_at']);
        $mode ??= new MaintenanceMode(['restaurant_id' => $data['restaurant_id']]);
        $mode->fill([
            'enabled' => $data['enabled'],
            'message' => $data['message'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'updated_by' => $request->user()->id,
        ])->save();

        SuperAdminAuditStream::record('maintenance_mode_update', [
            'scope' => $data['scope'],
            'before' => $before,
            'after' => $mode->only(['restaurant_id', 'enabled', 'message', 'starts_at', 'ends_at']),
        ], MaintenanceMode::class, $mode->id, 'critical');

        return response()->json([
            'success' => true,
            'message' => $data['enabled'] ? 'Đã bật maintenance mode.' : 'Đã tắt maintenance mode.',
        ]);
    }

    private function snapshot(): array
    {
        $services = $this->services();
        $queue = $this->queueSnapshot();
        $webhooks = $this->webhookSnapshot();
        $scheduler = $this->schedulerSnapshot();
        $probes = $this->probeSnapshot();

        return [
            'services' => $services,
            'queue' => $queue,
            'failedJobs' => $queue['failed_jobs'],
            'webhooks' => $webhooks,
            'scheduler' => $scheduler,
            'probes' => $probes,
            'maintenanceModes' => Schema::hasTable('maintenance_modes')
                ? MaintenanceMode::with('restaurant:id,name')->latest('updated_at')->get()->map(fn (MaintenanceMode $mode) => [
                    'id' => $mode->id,
                    'scope' => $mode->restaurant_id ? 'tenant' : 'global',
                    'restaurant_id' => $mode->restaurant_id,
                    'restaurant_name' => $mode->restaurant?->name,
                    'enabled' => (bool) $mode->enabled,
                    'message' => $mode->message,
                    'starts_at' => $mode->starts_at?->format('Y-m-d H:i'),
                    'ends_at' => $mode->ends_at?->format('Y-m-d H:i'),
                ])->values()->all()
                : [],
            'tenants' => Restaurant::query()->select(['id', 'name', 'lifecycle_status'])->orderBy('name')->limit(300)->get()->map(fn (Restaurant $restaurant) => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'lifecycle_status' => $restaurant->lifecycle_status,
            ])->values()->all(),
            'queueDriver' => config('queue.default'),
            'mailDriver' => config('mail.default'),
        ];
    }

    private function services(): array
    {
        $expected = [
            'mysql' => 'MySQL Database',
            'redis' => 'Redis Cache & Queue',
            'reverb' => 'Laravel Reverb Websockets',
            'meilisearch' => 'Meilisearch Engine',
            'email_service' => 'Email Service',
        ];

        try {
            $rows = ServiceMaintenanceStatus::all()->keyBy('service_key');
        } catch (\Throwable) {
            $rows = collect();
        }

        return collect($expected)->map(function (string $name, string $key) use ($rows): array {
            $row = $rows->get($key);
            [$host, $port] = $this->monitorService->getServiceConnectionDetails($key);
            $circuit = in_array($key, ['email_service'], true)
                ? app(CircuitBreaker::class)->for($key)->state()
                : null;

            return [
                'service_key' => $key,
                'name' => $row?->name ?: $name,
                'status' => $row?->last_status ?: 'unknown',
                'latency' => $row?->last_latency,
                'last_checked_at' => $row?->last_checked_at?->format('d/m/Y H:i:s'),
                'error_message' => $row?->error_message,
                'is_maintenance' => (bool) ($row?->is_maintenance ?? false),
                'host' => $host,
                'port' => $port,
                'circuit_breaker_state' => $circuit,
            ];
        })->values()->all();
    }

    private function queueSnapshot(): array
    {
        $queued = 0;
        $byQueue = [];
        $failed = [];

        if (Schema::hasTable('jobs')) {
            $queued = (int) DB::table('jobs')->count();
            $byQueue = DB::table('jobs')->select('queue', DB::raw('count(*) as total'))->groupBy('queue')->pluck('total', 'queue')->all();
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->latest('failed_at')->limit(50)->get()->map(function ($job): array {
                $payload = json_decode($job->payload, true) ?: [];

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'job' => $payload['displayName'] ?? $payload['job'] ?? 'Unknown job',
                    'exception' => mb_substr((string) $job->exception, 0, 800),
                    'failed_at' => $job->failed_at,
                ];
            })->values()->all();
        }

        return [
            'queued' => $queued,
            'by_queue' => $byQueue,
            'failed_count' => count($failed),
            'failed_jobs' => $failed,
        ];
    }

    private function webhookSnapshot(): array
    {
        if (! Schema::hasTable('webhook_deliveries')) {
            return ['pending' => 0, 'failed' => 0, 'success_24h' => 0, 'deliveries' => []];
        }

        $deliveries = WebhookDelivery::with('endpoint.restaurant:id,name')
            ->whereIn('status', ['pending', 'failed'])
            ->latest()
            ->limit(50)
            ->get();

        return [
            'pending' => WebhookDelivery::where('status', 'pending')->count(),
            'failed' => WebhookDelivery::where('status', 'failed')->count(),
            'success_24h' => WebhookDelivery::where('status', 'success')->where('delivered_at', '>=', now()->subDay())->count(),
            'deliveries' => $deliveries->map(fn (WebhookDelivery $delivery) => [
                'id' => $delivery->id,
                'event' => $delivery->event,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'response_code' => $delivery->response_code,
                'response_body' => $delivery->response_body,
                'restaurant_name' => $delivery->endpoint?->restaurant?->name,
                'created_at' => $delivery->created_at?->format('d/m/Y H:i:s'),
            ])->values()->all(),
        ];
    }

    private function schedulerSnapshot(): array
    {
        $heartbeatPath = storage_path('framework/scheduler-heartbeat.json');
        $heartbeat = is_file($heartbeatPath) ? json_decode((string) file_get_contents($heartbeatPath), true) : null;
        $lastRun = isset($heartbeat['last_run_at']) ? strtotime($heartbeat['last_run_at']) : null;

        try {
            $events = app(Schedule::class)->events();
            $taskCount = count($events);
        } catch (\Throwable) {
            $taskCount = 0;
        }

        return [
            'status' => $lastRun && $lastRun >= now()->subMinutes(10)->timestamp ? 'healthy' : 'stale',
            'last_run_at' => $heartbeat['last_run_at'] ?? null,
            'task_count' => $taskCount,
            'heartbeat_path' => $heartbeatPath,
        ];
    }

    private function probeSnapshot(): array
    {
        $database = ['status' => 'offline', 'latency_ms' => null, 'error' => null];
        $cache = ['status' => 'offline', 'latency_ms' => null, 'error' => null];
        $email = ['status' => config('mail.default') ? 'configured' : 'not_configured', 'driver' => config('mail.default')];

        $started = microtime(true);
        try {
            DB::select('SELECT 1');
            $database = ['status' => 'online', 'latency_ms' => round((microtime(true) - $started) * 1000, 2), 'error' => null];
        } catch (\Throwable $exception) {
            $database['error'] = $exception->getMessage();
        }

        $started = microtime(true);
        try {
            $key = 'operations-center:cache-probe';
            Cache::put($key, true, 10);
            $cache = [
                'status' => Cache::get($key) === true ? 'online' : 'offline',
                'latency_ms' => round((microtime(true) - $started) * 1000, 2),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            $cache['error'] = $exception->getMessage();
        }

        return ['database' => $database, 'cache' => $cache, 'email' => $email];
    }
}
