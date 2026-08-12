<?php

namespace App\Http\Controllers;

use App\Models\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    /**
     * Danh sách vị trí kho
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('warehouse.view') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $locations = WarehouseLocation::where('restaurant_id', $user->restaurant_id)
            ->with(['branch'])
            ->orderBy('zone')
            ->orderBy('location_code')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $locations,
        ]);
    }

    /**
     * Tạo vị trí lưu trữ mới
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('warehouse.manage') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'branch_id'       => 'required|integer|exists:restaurant_branches,id',
            'zone'            => 'required|string|max:50',
            'rack'            => 'nullable|string|max:50',
            'shelf'           => 'nullable|string|max:50',
            'bin'             => 'nullable|string|max:50',
            'location_code'   => 'required|string|max:100',
            'is_cold_storage' => 'nullable|boolean',
            'is_quarantine'   => 'nullable|boolean',
        ]);

        $location = WarehouseLocation::create([
            'restaurant_id'   => $user->restaurant_id,
            'branch_id'       => $data['branch_id'],
            'zone'            => $data['zone'],
            'rack'            => $data['rack'] ?? null,
            'shelf'           => $data['shelf'] ?? null,
            'bin'             => $data['bin'] ?? null,
            'location_code'   => strtoupper($data['location_code']),
            'is_cold_storage' => $data['is_cold_storage'] ?? false,
            'is_quarantine'   => $data['is_quarantine'] ?? false,
            'status'          => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã tạo vị trí lưu trữ kho thành công.',
            'data'    => $location,
        ]);
    }
}
