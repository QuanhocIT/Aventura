<?php

namespace App\Http\Controllers;

use App\Services\ServiceMonitorService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublicStatusController extends Controller
{
    /**
     * Render the public status page.
     */
    public function index(): Response
    {
        return Inertia::render('status/PublicStatus');
    }

    /**
     * Get JSON data for the public status page.
     */
    public function getStatusData(ServiceMonitorService $monitorService): JsonResponse
    {
        $uptimeStats = $monitorService->getUptimeStats30Days();

        // Check current statuses from JSON to support database-less mode
        $currentStatuses = [];
        $filePath = storage_path('framework/service-maintenance.json');
        if (file_exists($filePath)) {
            $currentStatuses = json_decode(file_get_contents($filePath), true) ?: [];
        }

        $activeIssuesCount = 0;
        $maintenanceCount = 0;

        foreach ($uptimeStats as $key => $stat) {
            $lastStatus = $currentStatuses[$key]['last_status'] ?? 'unknown';
            $isMaint = $stat['is_maintenance'];

            if ($isMaint) {
                $maintenanceCount++;
            } elseif ($lastStatus === 'offline') {
                $activeIssuesCount++;
            }
        }

        $overallStatus = 'operational';
        if ($activeIssuesCount > 0) {
            $overallStatus = 'outage';
        } elseif ($maintenanceCount > 0) {
            $overallStatus = 'maintenance';
        }

        return response()->json([
            'overall_status' => $overallStatus,
            'uptime_stats' => $uptimeStats,
            'current_statuses' => $currentStatuses,
        ]);
    }
}
