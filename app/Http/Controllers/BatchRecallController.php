<?php

namespace App\Http\Controllers;

use App\Models\BatchRecallOrder;
use App\Services\BatchRecallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\InventoryBatch;

class BatchRecallController extends Controller
{
    public function __construct(
        protected BatchRecallService $recallService
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $recalls = BatchRecallOrder::where('restaurant_id', $user->restaurant_id)
            ->with(['batch.ingredient', 'initiator'])
            ->orderBy('id', 'desc')
            ->get();

        $activeBatches = InventoryBatch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->with(['ingredient'])
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('inventory/BatchRecalls', [
            'recallOrders'  => $recalls,
            'activeBatches' => $activeBatches,
            'canManageWarehouse' => $user->isOwner()
                || $user->isSuperAdmin()
                || $user->hasRole('warehouse_manager')
                || $user->can('warehouse.manage')
                || $user->can('warehouse_governance.manage'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseView($user);
        $recalls = BatchRecallOrder::where('restaurant_id', $user->restaurant_id)
            ->with(['batch.ingredient', 'initiator'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['recall_orders' => $recalls]);
    }

    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseManage($user);

        $validated = $request->validate([
            'batch_id'     => 'required|integer',
            'severity'     => 'nullable|string|in:critical,high,medium',
            'reason'       => 'required|string',
            'action_taken' => 'nullable|string',
        ]);

        $recall = $this->recallService->initiateRecall($user->restaurant_id, (int) $validated['batch_id'], $validated, $user);

        return response()->json([
            'message'      => "Đã phát Lệnh Thu Hồi Lô Khẩn Cấp #{$recall->recall_code}. Lô đã bị khóa trên toàn hệ thống.",
            'recall_order' => $recall,
        ]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->authorizeWarehouseManage($user);
        $recall = BatchRecallOrder::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string',
        ]);

        $completed = $this->recallService->completeRecall($recall, $user, $validated['resolution_notes'] ?? null);

        return response()->json([
            'message'      => "Đã hoàn tất xử lý Lệnh Thu Hồi #{$completed->recall_code}.",
            'recall_order' => $completed,
        ]);
    }

    private function authorizeWarehouseView($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.view') || $user->can('warehouse_governance.view'),
            403,
            'Bạn không có quyền xem lệnh thu hồi Kho Tổng.'
        );
    }

    private function authorizeWarehouseManage($user): void
    {
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->hasRole('warehouse_manager') || $user->can('warehouse.manage') || $user->can('warehouse_governance.manage'),
            403,
            'Bạn không có quyền xử lý lệnh thu hồi Kho Tổng.'
        );
    }
}
