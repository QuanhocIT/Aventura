<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceMaintenanceStatus;
use App\Models\SystemMaintenanceSchedule;
use App\Services\ServiceMonitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceScheduleController extends Controller
{
    public function index(): Response
    {
        $schedules = SystemMaintenanceSchedule::latest('downtime_start')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'downtime_start' => $s->downtime_start->format('d/m/Y H:i'),
                'downtime_end' => $s->downtime_end->format('d/m/Y H:i'),
                'raw_start' => $s->downtime_start->toIso8601String(),
                'raw_end' => $s->downtime_end->toIso8601String(),
                'services' => $s->services,
                'banner_message' => $s->banner_message,
                'status' => $s->status,
                'notified_24h' => $s->notified_24h,
                'notified_1h' => $s->notified_1h,
            ]);

        $services = ServiceMaintenanceStatus::get(['service_key', 'name']);

        return Inertia::render('super-admin/settings/MaintenanceSchedules', [
            'schedules' => $schedules,
            'services' => $services,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'downtime_start' => ['required', 'date', 'after:now'],
            'downtime_end' => ['required', 'date', 'after:downtime_start'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['string', 'exists:service_maintenance_statuses,service_key'],
            'banner_message' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule = SystemMaintenanceSchedule::create([
            'title' => $validated['title'],
            'downtime_start' => $validated['downtime_start'],
            'downtime_end' => $validated['downtime_end'],
            'services' => $validated['services'],
            'banner_message' => $validated['banner_message'],
            'status' => 'scheduled',
            'notified_24h' => false,
            'notified_1h' => false,
        ]);

        AuditLog::create([
            'restaurant_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'maintenance_schedule_create',
            'subject_type' => SystemMaintenanceSchedule::class,
            'subject_id' => $schedule->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Đã lên lịch bảo trì thành công.');
    }

    public function destroy(SystemMaintenanceSchedule $schedule): RedirectResponse
    {
        $scheduleId = $schedule->id;
        $scheduleTitle = $schedule->title;

        // If active, disable maintenance for its services immediately
        if ($schedule->status === 'active') {
            $monitor = app(ServiceMonitorService::class);
            foreach ($schedule->services as $service) {
                $monitor->setMaintenance($service, false);
            }
        }

        $schedule->delete();

        AuditLog::create([
            'restaurant_id' => null,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'deleted',
            'action' => 'maintenance_schedule_delete',
            'subject_type' => SystemMaintenanceSchedule::class,
            'subject_id' => $scheduleId,
            'old_values' => ['title' => $scheduleTitle],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Đã hủy lịch bảo trì.');
    }
}
