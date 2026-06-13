<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Shipper;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipperController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurantId = $request->user()->restaurant_id;

        $shippers = Shipper::where('restaurant_id', $restaurantId)
            ->with('employee')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Shipper $s) => [
                'id' => $s->id,
                'employee_id' => $s->employee_id,
                'name' => $s->employee->full_name ?? $s->employee->name ?? '—',
                'phone' => $s->employee->phone ?? null,
                'vehicle_type' => $s->vehicle_type,
                'vehicle_plate' => $s->vehicle_plate,
                'is_active' => $s->is_active,
                'max_orders_per_batch' => $s->max_orders_per_batch,
                'max_capacity_kg' => $s->max_capacity_kg,
            ]);

        $shipperEmployeeIds = $shippers->pluck('employee_id')->toArray();

        $availableEmployees = Employee::where('restaurant_id', $restaurantId)
            ->whereNotIn('id', $shipperEmployeeIds)
            ->where('status', 'active')
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name ?? $e->name,
                'phone' => $e->phone ?? null,
            ]);

        return Inertia::render('delivery/shippers/Index', [
            'shippers' => $shippers,
            'available_employees' => $availableEmployees,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $restaurantId = $request->user()->restaurant_id;
        $validated = $request->validate([
            'employee_id' => "required|integer|exists:employees,id,restaurant_id,{$restaurantId}",
            'vehicle_type' => 'required|in:bike,motorbike,car',
            'vehicle_plate' => 'nullable|string|max:20',
            'max_orders_per_batch' => 'nullable|integer|min:1|max:20',
            'max_capacity_kg' => 'nullable|numeric|min:1|max:500',
        ]);

        $alreadyShipper = Shipper::where('employee_id', $validated['employee_id'])->exists();
        if ($alreadyShipper) {
            return response()->json(['message' => 'Nhân viên này đã là shipper.'], 422);
        }

        $shipper = Shipper::create([
            'restaurant_id' => $restaurantId,
            'employee_id' => $validated['employee_id'],
            'vehicle_type' => $validated['vehicle_type'],
            'vehicle_plate' => $validated['vehicle_plate'] ?? null,
            'is_active' => true,
            'max_orders_per_batch' => $validated['max_orders_per_batch'] ?? 5,
            'max_capacity_kg' => $validated['max_capacity_kg'] ?? 20,
        ]);

        return response()->json([
            'message' => 'Shipper đã được thêm.',
            'shipper_id' => $shipper->id,
        ], 201);
    }

    public function update(Request $request, Shipper $shipper): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_type' => 'sometimes|in:bike,motorbike,car',
            'vehicle_plate' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'max_orders_per_batch' => 'sometimes|integer|min:1|max:20',
            'max_capacity_kg' => 'sometimes|numeric|min:1|max:500',
        ]);

        $shipper->update($validated);

        return response()->json(['message' => 'Cập nhật shipper thành công.']);
    }

    public function destroy(Shipper $shipper): JsonResponse
    {
        $hasActiveBatch = $shipper->batches()->whereIn('status', ['dispatched', 'in_progress'])->exists();
        if ($hasActiveBatch) {
            return response()->json(['message' => 'Không thể xóa shipper đang có batch hoạt động.'], 422);
        }

        $shipper->delete();

        return response()->json(['message' => 'Đã xóa shipper.']);
    }
}
