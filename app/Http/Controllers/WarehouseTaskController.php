<?php

namespace App\Http\Controllers;

use App\Models\SupplyRequest;
use App\Models\WarehouseTaskAssignment;
use App\Services\CentralWarehouseService;
use App\Services\WarehouseTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WarehouseTaskController extends Controller
{
    public function __construct(
        protected WarehouseTaskService $taskService,
        protected CentralWarehouseService $warehouseService,
    ) {}

    /**
     * Lấy dữ liệu Bảng công việc Kho Tổng (Task Board) cho nhân viên kho
     */
    public function taskBoardData(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('supply_requests.view') || $user->can('warehouse.view') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $data = $this->taskService->getTaskBoardData($user->restaurant_id);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Trưởng kho giao một chặng việc cho nhân viên Kho Tổng.
     */
    public function assignWarehouseTask(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage'),
            403,
            'Chỉ Trưởng kho Tổng mới có quyền điều phối nhân viên Kho Tổng.'
        );

        $data = $request->validate([
            'supply_request_id' => 'required|integer',
            'assigned_to'       => 'required|integer',
            'task_type'         => 'required|string|in:picking,handover',
            'priority'          => 'required|string|in:normal,high,urgent',
            'due_at'            => 'nullable|date',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $task = $this->taskService->assignTask($user, $data);

        return response()->json([
            'success' => true,
            'message' => "Đã giao việc thành công.",
            'data'    => $task->load(['assignee.employee', 'supplyRequest.toBranch']),
        ]);
    }

    /**
     * Nhân viên hoặc quản lý cập nhật tiến độ nhiệm vụ được giao.
     */
    public function updateWarehouseTaskStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $task = WarehouseTaskAssignment::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $isManager = $user->isOwner() || $user->isSuperAdmin() || $user->can('warehouse.manage');
        abort_unless($isManager || (int) $task->assigned_to === (int) $user->id, 403, 'Bạn không được cập nhật nhiệm vụ này.');

        $data = $request->validate([
            'status' => 'required|string|in:assigned,in_progress,completed,cancelled',
            'notes'  => 'nullable|string|max:500',
        ]);

        $updatedTask = $this->taskService->updateTaskStatus($task, $user, $data['status'], $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật tiến độ nhiệm vụ Kho Tổng.',
            'data'    => $updatedTask->fresh(['assignee.employee', 'supplyRequest.toBranch']),
        ]);
    }

    /**
     * Tải / Xem ảnh chứng từ và chữ ký bàn giao (bảo vệ quyền truy cập).
     */
    public function viewProof(Request $request, int $id, string $type)
    {
        $user = $request->user();
        $supplyRequest = SupplyRequest::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        abort_unless(
            $user->canAccessBranch((int) $supplyRequest->to_branch_id)
            || $user->canAccessBranch((int) $supplyRequest->from_branch_id)
            || $user->isWarehouseManager()
            || $user->isOwner()
            || $user->isSuperAdmin(),
            403,
            'Bạn không có quyền xem chứng từ của đơn cấp phát này.'
        );

        $path = match ($type) {
            'receipt_photo' => $supplyRequest->receipt_photo_path,
            'signature', 'receiver_signature' => $supplyRequest->receiver_signature_path,
            default => null,
        };

        abort_unless($path, 404, 'Không tìm thấy chứng từ.');

        if (Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path));
        }

        abort(404, 'File chứng từ không tồn tại trong vùng lưu trữ riêng tư.');
    }
}
