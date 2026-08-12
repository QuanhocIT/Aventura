<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WarehouseFraudCase;
use App\Services\WarehouseFraudDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseFraudCaseController extends Controller
{
    public function __construct(
        protected WarehouseFraudDetectionService $fraudDetectionService,
    ) {}

    /**
     * Danh sách Hồ sơ Cảnh báo Gian lận
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('warehouse_governance.view') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $cases = WarehouseFraudCase::where('restaurant_id', $user->restaurant_id)
            ->with(['assignedTo', 'resolvedBy'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $cases,
        ]);
    }

    /**
     * Phân công người xử lý case
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('warehouse_governance.manage') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'assigned_to' => 'required|integer|exists:users,id',
            'deadline_at' => 'nullable|date',
        ]);

        $case = WarehouseFraudCase::where('restaurant_id', $user->restaurant_id)->findOrFail($id);
        $assignee = User::where('restaurant_id', $user->restaurant_id)->findOrFail($data['assigned_to']);

        $updated = $this->fraudDetectionService->assignCase(
            $case,
            $assignee,
            ! empty($data['deadline_at']) ? \Illuminate\Support\Carbon::parse($data['deadline_at']) : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã phân công người phụ trách xử lý hồ sơ gian lận thành công.',
            'data'    => $updated,
        ]);
    }

    /**
     * Cập nhật trạng thái và kết luận điều tra
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('warehouse_governance.manage') || $user->isOwner() || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'status'           => ['required', 'string', Rule::in(['open', 'investigating', 'resolved', 'closed', 'appealed'])],
            'resolution_notes' => 'nullable|string',
            'evidence_urls'    => 'nullable|array',
            'evidence_urls.*'  => 'string',
        ]);

        $case = WarehouseFraudCase::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        $updated = $this->fraudDetectionService->updateCaseStatus(
            $case,
            $data['status'],
            $user,
            $data['resolution_notes'] ?? null,
            $data['evidence_urls'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái hồ sơ gian lận thành công.',
            'data'    => $updated,
        ]);
    }
}
