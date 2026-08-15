<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentMaintenanceLog;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        $equipment = Equipment::where('restaurant_id', $restaurantId)
            ->withCount('maintenanceLogs')
            ->get()
            ->map(fn ($e) => array_merge($e->toArray(), [
                'warranty_expiry' => $e->warrantyExpiry(),
                'under_warranty' => $e->isUnderWarranty(),
                'age_months' => $e->ageInMonths(),
                'total_maintenance_cost' => $e->totalMaintenanceCost(),
            ]));

        $recentLogs = EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
            ->with(['equipment:id,name', 'reporter:id,name'])
            ->latest()
            ->take(20)
            ->get();

        $stats = [
            'total' => Equipment::where('restaurant_id', $restaurantId)->count(),
            'active' => Equipment::where('restaurant_id', $restaurantId)->where('status', 'active')->count(),
            'broken' => Equipment::where('restaurant_id', $restaurantId)->where('status', 'broken')->count(),
            'pending_maintenance' => EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
                ->whereIn('status', ['pending', 'in_progress'])->count(),
            'total_cost_ytd' => (float) EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
                ->where('status', 'completed')
                ->whereYear('completed_date', now()->year)
                ->sum('cost'),
        ];

        return Inertia::render('equipment/Index', compact('equipment', 'recentLogs', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:kitchen,refrigeration,cleaning,pos,hvac,furniture,other'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model_number' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['restaurant_id'] = $request->user()->restaurant_id;
        $data['code'] = 'EQ-'.strtoupper(Str::random(6));

        Equipment::create($data);

        return back()->with('success', "Đã thêm thiết bị \"{$data['name']}\".");
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:kitchen,refrigeration,cleaning,pos,hvac,furniture,other'],
            'status' => ['required', 'in:active,maintenance,broken,retired'],
            'brand' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $equipment->update($data);

        return back()->with('success', 'Đã cập nhật thiết bị.');
    }

    public function reportIssue(Request $request): RedirectResponse
    {
        $this->authorizeReporting($request);

        $data = $request->validate([
            'equipment_id' => ['required', TenantRule::exists('equipment')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:scheduled,repair,inspection,replacement'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'scheduled_date' => ['nullable', 'date'],
        ]);

        EquipmentMaintenanceLog::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'equipment_id' => $data['equipment_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cost' => $data['cost'] ?? 0,
            'status' => 'pending',
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'reported_by' => $request->user()->id,
        ]);

        Equipment::where('id', $data['equipment_id'])->update(['status' => $data['type'] === 'repair' ? 'broken' : 'maintenance']);

        return back()->with('success', 'Đã ghi nhận báo cáo bảo trì.');
    }

    public function completeLog(Request $request, EquipmentMaintenanceLog $log): RedirectResponse
    {
        $this->authorizeManagement($request);

        $data = $request->validate([
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $log->update([
            'status' => 'completed',
            'completed_date' => now(),
            'cost' => $data['cost'] ?? $log->cost,
        ]);

        $log->equipment->update(['status' => 'active']);

        return back()->with('success', 'Đã hoàn thành bảo trì.');
    }

    public function destroy(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorizeManagement($request);

        $equipment->delete();

        return back()->with('success', 'Đã xóa thiết bị.');
    }

    private function authorizeManagement(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isOwner() || $user->isSuperAdmin() || $user->can('equipment.manage')),
            403,
            'Bạn không có quyền quản lý thiết bị.'
        );
    }

    private function authorizeReporting(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isOwner() || $user->isSuperAdmin() || $user->can('equipment.report') || $user->can('equipment.manage')),
            403,
            'Bạn không có quyền báo cáo sự cố thiết bị.'
        );
    }
}
