<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ServiceMaintenanceStatus;
use App\Services\ServiceMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceMonitorController extends Controller
{
    public function __construct(private ServiceMonitorService $monitorService)
    {
    }

    /**
     * Display service monitor panel.
     */
    public function index(): Response
    {
        $services = ServiceMaintenanceStatus::all()->map(function ($s) {
            [$host, $port] = $this->monitorService->getServiceConnectionDetails($s->service_key);
            return array_merge($s->toArray(), [
                'host' => $host,
                'port' => $port,
            ]);
        });

        return Inertia::render('super-admin/service-monitor/Index', [
            'services' => $services
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
            return array_merge($s->toArray(), [
                'host' => $host,
                'port' => $port,
            ]);
        });

        return response()->json([
            'success' => true,
            'results' => $results,
            'services' => $services
        ]);
    }

    /**
     * Toggle maintenance mode for a service.
     */
    public function toggleMaintenance(Request $request, string $serviceKey): JsonResponse
    {
        $request->validate([
            'is_maintenance' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $isMaintenance = (bool)$request->input('is_maintenance');
        $message = $request->input('message');

        $updated = $this->monitorService->setMaintenance($serviceKey, $isMaintenance, $message);

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
        $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $message = $request->input('message');
        $isMaintenance = $this->monitorService->isMaintenance($serviceKey);

        $updated = $this->monitorService->setMaintenance($serviceKey, $isMaintenance, $message);

        return response()->json([
            'success' => true,
            'message' => $message,
            'db_updated' => $updated,
        ]);
    }
}
