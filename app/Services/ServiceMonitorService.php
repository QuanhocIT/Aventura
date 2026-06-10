<?php

namespace App\Services;

use App\Models\ServiceMaintenanceStatus;
use App\Models\ServiceHealthLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceMonitorService
{
    /**
     * Check if a service is marked under maintenance.
     */
    public function isMaintenance(string $serviceKey): bool
    {
        $filePath = storage_path('framework/service-maintenance.json');
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if (is_array($data) && isset($data[$serviceKey])) {
                return (bool) ($data[$serviceKey]['is_maintenance'] ?? false);
            }
        }
        return false;
    }

    /**
     * Get maintenance message for a service.
     */
    public function getMaintenanceMessage(string $serviceKey): ?string
    {
        $filePath = storage_path('framework/service-maintenance.json');
        if (file_exists($filePath)) {
            $data = json_decode(file_get_contents($filePath), true);
            if (is_array($data) && isset($data[$serviceKey])) {
                return $data[$serviceKey]['message'] ?? null;
            }
        }
        return null;
    }

    /**
     * Write maintenance config map to storage/framework/service-maintenance.json
     */
    public function writeJsonConfig(array $config): void
    {
        try {
            $filePath = storage_path('framework/service-maintenance.json');
            file_put_contents($filePath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::error('ServiceMonitorService: Lỗi ghi file cấu hình bảo trì', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync JSON config file from current database table state
     */
    public function syncJsonConfigFromDb(): void
    {
        try {
            $statuses = ServiceMaintenanceStatus::all();
            $jsonConfig = [];
            
            // If file exists, read it to merge pings
            $existingJson = [];
            $filePath = storage_path('framework/service-maintenance.json');
            if (file_exists($filePath)) {
                $existingJson = json_decode(file_get_contents($filePath), true) ?: [];
            }

            foreach ($statuses as $service) {
                $jsonConfig[$service->service_key] = [
                    'is_maintenance' => (bool)$service->is_maintenance,
                    'message' => $service->maintenance_message,
                    'last_status' => $service->last_status ?: ($existingJson[$service->service_key]['last_status'] ?? 'unknown'),
                    'last_latency' => (float)$service->last_latency ?: ($existingJson[$service->service_key]['last_latency'] ?? 0.0),
                    'last_checked_at' => $service->last_checked_at ? $service->last_checked_at->toDateTimeString() : ($existingJson[$service->service_key]['last_checked_at'] ?? null),
                ];
            }

            $this->writeJsonConfig($jsonConfig);
        } catch (\Throwable $e) {
            Log::warning('ServiceMonitorService: Không đồng bộ được database sang JSON (có thể DB offline)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update maintenance status for a service
     */
    public function setMaintenance(string $serviceKey, bool $isMaintenance, ?string $message = null): bool
    {
        $dbUpdated = false;
        
        try {
            $service = ServiceMaintenanceStatus::where('service_key', $serviceKey)->first();
            if ($service) {
                $service->update([
                    'is_maintenance' => $isMaintenance,
                    'maintenance_message' => $message ?: $service->maintenance_message,
                ]);
                $dbUpdated = true;
            }
        } catch (\Throwable $e) {
            Log::warning("ServiceMonitorService: Không cập nhật được trạng thái bảo trì {$serviceKey} vào database", ['error' => $e->getMessage()]);
        }

        // Always sync json config file
        $filePath = storage_path('framework/service-maintenance.json');
        $jsonConfig = [];
        if (file_exists($filePath)) {
            $jsonConfig = json_decode(file_get_contents($filePath), true) ?: [];
        }

        if (!isset($jsonConfig[$serviceKey])) {
            $jsonConfig[$serviceKey] = [
                'is_maintenance' => $isMaintenance,
                'message' => $message ?: 'Đang bảo trì',
                'last_status' => 'unknown',
                'last_latency' => 0.0,
                'last_checked_at' => null,
            ];
        } else {
            $jsonConfig[$serviceKey]['is_maintenance'] = $isMaintenance;
            if ($message !== null) {
                $jsonConfig[$serviceKey]['message'] = $message;
            }
        }

        $this->writeJsonConfig($jsonConfig);

        return $dbUpdated;
    }

    /**
     * Get host and port details dynamically from config
     */
    public function getServiceConnectionDetails(string $serviceKey): array
    {
        switch ($serviceKey) {
            case 'mysql':
                return [
                    config('database.connections.mysql.host', '127.0.0.1'),
                    (int) config('database.connections.mysql.port', 3306)
                ];
            case 'redis':
                return [
                    config('database.redis.default.host', '127.0.0.1'),
                    (int) config('database.redis.default.port', 6379)
                ];
            case 'reverb':
                $host = config('reverb.apps.apps.0.options.host') ?: env('REVERB_HOST', '127.0.0.1');
                $port = config('reverb.apps.apps.0.options.port') ?: env('REVERB_PORT', 8080);
                return [$host, (int) $port];
            case 'meilisearch':
                $url = config('scout.meilisearch.host', 'http://localhost:7700');
                $parsed = parse_url($url);
                return [
                    $parsed['host'] ?? '127.0.0.1',
                    (int) ($parsed['port'] ?? 7700)
                ];
            case 'email_service':
                $url = config('services.email_microservice.url', 'http://localhost:8001');
                $parsed = parse_url($url);
                return [
                    $parsed['host'] ?? '127.0.0.1',
                    (int) ($parsed['port'] ?? 8001)
                ];
            case 'chatbot_service':
                $url = config('services.chatbot.url', 'http://localhost:8002');
                $parsed = parse_url($url);
                return [
                    $parsed['host'] ?? '127.0.0.1',
                    (int) ($parsed['port'] ?? 8002)
                ];
            default:
                return ['127.0.0.1', 0];
        }
    }

    /**
     * Ping a service port to measure latency and verify online status.
     */
    public function pingService(string $serviceKey, string $host, int $port): array
    {
        // Remove protocol if exists
        if (str_contains($host, '://')) {
            $parsed = parse_url($host);
            $host = $parsed['host'] ?? $host;
        }

        $startTime = microtime(true);
        $errno = 0;
        $errstr = '';

        // 2-second TCP socket timeout
        $connection = @fsockopen($host, $port, $errno, $errstr, 2.0);
        $latency = (microtime(true) - $startTime) * 1000;

        if (is_resource($connection)) {
            fclose($connection);

            // Deep DB verification if port is listening
            if ($serviceKey === 'mysql') {
                try {
                    DB::connection()->getPdo();
                } catch (\Throwable $e) {
                    return [
                        'status' => 'offline',
                        'latency' => $latency,
                        'error' => 'MySQL port open, but connection failed: ' . $e->getMessage()
                    ];
                }
            }

            return [
                'status' => 'online',
                'latency' => round($latency, 2),
                'error' => null
            ];
        }

        return [
            'status' => 'offline',
            'latency' => 0.0,
            'error' => "TCP connection failed to {$host}:{$port}. Error {$errno}: {$errstr}"
        ];
    }

    /**
     * Check all services, write database history logs and sync storage JSON.
     */
    public function checkAll(): array
    {
        $results = [];
        $dbAvailable = true;
        
        try {
            $statuses = ServiceMaintenanceStatus::all();
        } catch (\Throwable $e) {
            $dbAvailable = false;
            // Fallback static configuration if DB connection fails
            $statuses = collect([
                (object)['service_key' => 'mysql', 'name' => 'MySQL Database', 'port' => 3306, 'is_maintenance' => false, 'maintenance_message' => 'MySQL Database offline'],
                (object)['service_key' => 'redis', 'name' => 'Redis Cache & Queue', 'port' => 6379, 'is_maintenance' => false, 'maintenance_message' => 'Redis offline'],
                (object)['service_key' => 'reverb', 'name' => 'Laravel Reverb Websockets', 'port' => 8080, 'is_maintenance' => false, 'maintenance_message' => 'Reverb offline'],
                (object)['service_key' => 'meilisearch', 'name' => 'Meilisearch Engine', 'port' => 7700, 'is_maintenance' => false, 'maintenance_message' => 'Meilisearch offline'],
                (object)['service_key' => 'email_service', 'name' => 'Python Email Service', 'port' => 8001, 'is_maintenance' => false, 'maintenance_message' => 'Email offline'],
                (object)['service_key' => 'chatbot_service', 'name' => 'Python Chatbot Service', 'port' => 8002, 'is_maintenance' => false, 'maintenance_message' => 'Chatbot offline'],
            ]);
        }

        $jsonConfig = [];
        $existingJson = [];
        $filePath = storage_path('framework/service-maintenance.json');
        if (file_exists($filePath)) {
            $existingJson = json_decode(file_get_contents($filePath), true) ?: [];
        }

        foreach ($statuses as $service) {
            [$host, $port] = $this->getServiceConnectionDetails($service->service_key);
            $ping = $this->pingService($service->service_key, $host, $port);

            if ($dbAvailable) {
                try {
                    $dbService = ServiceMaintenanceStatus::where('service_key', $service->service_key)->first();
                    if ($dbService) {
                        $dbService->update([
                            'last_checked_at' => now(),
                            'last_status' => $ping['status'],
                            'last_latency' => $ping['latency'],
                            'error_message' => $ping['error'],
                        ]);
                    }

                    ServiceHealthLog::create([
                        'service_key' => $service->service_key,
                        'status' => $ping['status'],
                        'latency' => $ping['latency'],
                        'error_message' => $ping['error'],
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $dbEx) {
                    Log::warning("ServiceMonitorService: Lỗi ghi log database cho {$service->service_key}", ['error' => $dbEx->getMessage()]);
                }
            }

            $results[$service->service_key] = $ping;

            $isMaintenance = isset($service->is_maintenance) ? $service->is_maintenance : ($existingJson[$service->service_key]['is_maintenance'] ?? false);
            $mMessage = isset($service->maintenance_message) ? $service->maintenance_message : ($existingJson[$service->service_key]['message'] ?? 'Dịch vụ đang bảo trì.');

            $jsonConfig[$service->service_key] = [
                'is_maintenance' => (bool)$isMaintenance,
                'message' => $mMessage,
                'last_status' => $ping['status'],
                'last_latency' => $ping['latency'],
                'last_checked_at' => now()->toDateTimeString(),
            ];
        }

        $this->writeJsonConfig($jsonConfig);

        return $results;
    }

    /**
     * Gather 30-day historical uptime timeline metrics.
     */
    public function getUptimeStats30Days(): array
    {
        $stats = [];
        $services = ['mysql', 'redis', 'reverb', 'meilisearch', 'email_service', 'chatbot_service'];
        
        $serviceNames = [
            'mysql' => 'MySQL Database',
            'redis' => 'Redis Cache & Queue',
            'reverb' => 'Laravel Reverb Websockets',
            'meilisearch' => 'Meilisearch Engine',
            'email_service' => 'Python Email Service',
            'chatbot_service' => 'Python Chatbot Service',
        ];

        $startDate = now()->subDays(29)->startOfDay();

        try {
            $logs = ServiceHealthLog::query()
                ->where('created_at', '>=', $startDate)
                ->get();
            $groupedLogs = $logs->groupBy('service_key');
        } catch (\Throwable $e) {
            Log::warning('ServiceMonitorService: Không truy vấn được bảng logs (DB offline)', ['error' => $e->getMessage()]);
            $groupedLogs = collect();
        }

        foreach ($services as $key) {
            $serviceLogs = $groupedLogs->get($key, collect());

            $totalLogsCount = $serviceLogs->count();
            $onlineLogsCount = $serviceLogs->where('status', 'online')->count();

            $overallUptime = $totalLogsCount > 0 
                ? round(($onlineLogsCount / $totalLogsCount) * 100, 2) 
                : 100.0;

            $dailyHistory = [];
            for ($i = 29; $i >= 0; $i--) {
                $targetDay = now()->subDays($i);
                $dayStart = $targetDay->copy()->startOfDay();
                $dayEnd = $targetDay->copy()->endOfDay();

                $dayLogs = $serviceLogs->filter(function ($log) use ($dayStart, $dayEnd) {
                    return $log->created_at >= $dayStart && $log->created_at <= $dayEnd;
                });

                $dayTotal = $dayLogs->count();
                $dayOnline = $dayLogs->where('status', 'online')->count();
                $dayLatencyAvg = $dayLogs->where('status', 'online')->avg('latency') ?: 0.0;

                if ($dayTotal === 0) {
                    $status = 'no_data';
                    $uptimePct = 100.0;
                } else {
                    $uptimePct = round(($dayOnline / $dayTotal) * 100, 2);
                    if ($uptimePct >= 99.5) {
                        $status = 'operational';
                    } elseif ($uptimePct >= 95.0) {
                        $status = 'partial_outage';
                    } else {
                        $status = 'major_outage';
                    }
                }

                $dailyHistory[] = [
                    'date' => $dayStart->format('Y-m-d'),
                    'display_date' => $dayStart->format('d/m/Y'),
                    'uptime_percentage' => $uptimePct,
                    'average_latency' => round($dayLatencyAvg, 2),
                    'status' => $status,
                ];
            }

            $stats[$key] = [
                'service_key' => $key,
                'name' => $serviceNames[$key] ?? ucfirst($key),
                'overall_uptime' => $overallUptime,
                'daily_history' => $dailyHistory,
                'is_maintenance' => $this->isMaintenance($key),
                'maintenance_message' => $this->getMaintenanceMessage($key),
            ];
        }

        return $stats;
    }
}
