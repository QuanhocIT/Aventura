<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceMaintenanceStatus;
use App\Services\CircuitBreaker;
use App\Services\ServiceMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ServiceMonitorController extends Controller
{
    public function __construct(private ServiceMonitorService $monitorService) {}

    /**
     * Display service monitor panel.
     */
    public function index(): Response
    {
        $services = ServiceMaintenanceStatus::all()->map(function ($s) {
            [$host, $port] = $this->monitorService->getServiceConnectionDetails($s->service_key);

            $circuitBreakerState = null;
            if (in_array($s->service_key, ['email_service', 'chatbot_service', 'analytics_service'])) {
                $circuitBreakerState = app(CircuitBreaker::class)->for($s->service_key)->state();
            }

            return array_merge($s->toArray(), [
                'host' => $host,
                'port' => $port,
                'circuit_breaker_state' => $circuitBreakerState,
            ]);
        });

        return Inertia::render('super-admin/service-monitor/Index', [
            'services' => $services,
        ]);
    }

    /**
     * Force-run check on all services and return results.
     */
    public function pingAll(): JsonResponse
    {
        $results = $this->monitorService->checkAll();

        $services = ServiceMaintenanceStatus::all()->map(function ($s) {
            [$host, $port] = $this->monitorService->getServiceConnectionDetails($s->service_key);

            $circuitBreakerState = null;
            if (in_array($s->service_key, ['email_service', 'chatbot_service', 'analytics_service'])) {
                $circuitBreakerState = app(CircuitBreaker::class)->for($s->service_key)->state();
            }

            return array_merge($s->toArray(), [
                'host' => $host,
                'port' => $port,
                'circuit_breaker_state' => $circuitBreakerState,
            ]);
        });

        return response()->json([
            'success' => true,
            'results' => $results,
            'services' => $services,
        ]);
    }

    /**
     * Toggle maintenance mode for a service.
     */
    public function toggleMaintenance(Request $request, string $serviceKey): JsonResponse
    {
        $service = ServiceMaintenanceStatus::where('service_key', $serviceKey)->firstOrFail();
        $request->validate([
            'is_maintenance' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $isMaintenance = (bool) $request->input('is_maintenance');
        $message = $request->input('message');

        $updated = $this->monitorService->setMaintenance($service->service_key, $isMaintenance, $message);
        $this->audit($request, 'service_maintenance_toggle', $service->service_key, [
            'is_maintenance' => $isMaintenance,
            'message' => $message,
        ]);

        return response()->json([
            'success' => true,
            'is_maintenance' => $isMaintenance,
            'message' => $message,
            'db_updated' => $updated,
        ]);
    }

    /**
     * Update maintenance warning message for a service.
     */
    public function updateMessage(Request $request, string $serviceKey): JsonResponse
    {
        $service = ServiceMaintenanceStatus::where('service_key', $serviceKey)->firstOrFail();
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = $request->input('message');
        $isMaintenance = $this->monitorService->isMaintenance($service->service_key);

        $updated = $this->monitorService->setMaintenance($service->service_key, $isMaintenance, $message);
        $this->audit($request, 'service_maintenance_message_update', $service->service_key, ['message' => $message]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'db_updated' => $updated,
        ]);
    }

    /**
     * Reset Circuit Breaker for a service.
     */
    public function resetCircuitBreaker(Request $request, string $serviceKey): JsonResponse
    {
        $service = ServiceMaintenanceStatus::where('service_key', $serviceKey)->firstOrFail();
        Cache::forget("circuit_breaker:{$serviceKey}");
        Cache::forget("circuit_breaker_probe:{$serviceKey}");
        $this->audit($request, 'service_circuit_breaker_reset', $service->service_key, []);

        return response()->json([
            'success' => true,
            'message' => "Đã reset Circuit Breaker cho {$serviceKey} thành công.",
        ]);
    }

    private function audit(Request $request, string $action, string $serviceKey, array $values): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()?->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => $action,
            'subject_type' => ServiceMaintenanceStatus::class,
            'subject_id' => ServiceMaintenanceStatus::where('service_key', $serviceKey)->value('id'),
            'old_values' => null,
            'new_values' => ['service_key' => $serviceKey] + $values,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
