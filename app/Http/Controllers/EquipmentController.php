<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentMaintenanceLog;
use App\Support\Tenant\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;
        $branchId = $this->tenantContext->activeBranchId();

        $equipment = Equipment::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->withCount('maintenanceLogs')
            ->get()
            ->map(fn ($e) => array_merge($e->toArray(), [
                'warranty_expiry' => $e->warrantyExpiry(),
                'under_warranty' => $e->isUnderWarranty(),
                'age_months' => $e->ageInMonths(),
                'total_maintenance_cost' => $e->totalMaintenanceCost(),
            ]));

        $recentLogs = EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with(['equipment:id,name', 'reporter:id,name'])
            ->latest()
            ->take(20)
            ->get();

        $stats = [
            'total' => Equipment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->count(),
            'active' => Equipment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'active')->count(),
            'broken' => Equipment::where('restaurant_id', $restaurantId)->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))->where('status', 'broken')->count(),
            'pending_maintenance' => EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->whereIn('status', ['pending', 'in_progress'])->count(),
            'total_cost_ytd' => (float) EquipmentMaintenanceLog::where('restaurant_id', $restaurantId)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('status', 'completed')
                ->whereYear('completed_date', now()->year)
                ->sum('cost'),
        ];

        return Inertia::render('equipment/Index', [
            'equipment' => $equipment,
            'recentLogs' => $recentLogs,
            'stats' => $stats,
            'branchContext' => [
                'scope' => $this->tenantContext->scope(),
                'active_branch_id' => $branchId,
            ],
        ]);
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
        $data['branch_id'] = $this->tenantContext->isBranchScoped()
            ? $this->tenantContext->activeBranchId()
            : null;

        Equipment::create($data);

        return back()->with('success', "Đã thêm thiết bị \"{$data['name']}\".");
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeEquipmentScope($request, $equipment);

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

        $equipment = Equipment::where('restaurant_id', $request->user()->restaurant_id)
            ->whereKey($data['equipment_id'])
            ->firstOrFail();

        // Thiết bị được tạo trước khi có branch_id được xem là dữ liệu legacy.
        // Khi phát sinh báo hỏng từ một chi nhánh cụ thể, gắn nó vào chi nhánh
        // đang thao tác để không tạo maintenance log ngoài phạm vi hiển thị.
        if ($this->tenantContext->isBranchScoped() && $equipment->branch_id === null) {
            $equipment->update(['branch_id' => $this->tenantContext->activeBranchId()]);
            $equipment->refresh();
        }
        $this->authorizeEquipmentScope($request, $equipment);

        EquipmentMaintenanceLog::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'equipment_id' => $data['equipment_id'],
            'branch_id' => $equipment->branch_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cost' => $data['cost'] ?? 0,
            'status' => 'pending',
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'reported_by' => $request->user()->id,
        ]);

        $equipment->update(['status' => $data['type'] === 'repair' ? 'broken' : 'maintenance']);

        return back()->with('success', 'Đã ghi nhận báo cáo bảo trì.');
    }

    public function completeLog(Request $request, EquipmentMaintenanceLog $log): RedirectResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeLogScope($request, $log);

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
        $this->authorizeEquipmentScope($request, $equipment);

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

    private function authorizeEquipmentScope(Request $request, Equipment $equipment): void
    {
        abort_if((int) $equipment->restaurant_id !== (int) $request->user()->restaurant_id, 403);

        if ($this->tenantContext->isBranchScoped()) {
            abort_if((int) $equipment->branch_id !== (int) $this->tenantContext->activeBranchId(), 403, 'Thiết bị không thuộc chi nhánh đang chọn.');
        }
    }

    private function authorizeLogScope(Request $request, EquipmentMaintenanceLog $log): void
    {
        abort_if((int) $log->restaurant_id !== (int) $request->user()->restaurant_id, 403);
        $equipment = $log->equipment;
        abort_if(! $equipment, 404);
        $this->authorizeEquipmentScope($request, $equipment);

        if ($this->tenantContext->isBranchScoped()) {
            abort_if((int) $log->branch_id !== (int) $this->tenantContext->activeBranchId(), 403, 'Nhật ký bảo trì không thuộc chi nhánh đang chọn.');
        }
    }
}
