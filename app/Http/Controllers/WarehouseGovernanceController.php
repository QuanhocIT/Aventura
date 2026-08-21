<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WarehouseGovernanceService;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseGovernanceController extends Controller
{
    public function __construct(
        protected WarehouseGovernanceService $governanceService
    ) {}

    /**
     * Governance Dashboard Page (Inertia View for Trưởng Kho)
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $summary = $this->governanceService->getRiskAndReliabilitySummary($user->restaurant_id);
        $employees = User::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['warehouse_staff', 'warehouse_manager', 'manager', 'branch_staff', 'staff']))
            ->select('id', 'name', 'email', 'branch_id', 'warehouse_branch_id')
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('inventory/WarehouseGovernance', [
            'summary' => $summary,
            'rules' => $summary['rules'],
            'recentDisputes' => $summary['recent_disputes'],
            'employees' => $employees,
        ]);
    }

    /**
     * Update Governance Rules & Approval Thresholds
     */
    public function updateRules(Request $request): JsonResponse
    {
        $request->validate([
            'max_auto_approve_variance_amount' => 'required|numeric|min:0',
            'max_auto_approve_variance_percent' => 'required|numeric|min:0|max:100',
            'require_seal_code_on_dispatch' => 'required|boolean',
            'auto_dispute_on_discrepancy' => 'required|boolean',
            'penalty_deduction_enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        $updatedRule = $this->governanceService->updateRules($user->restaurant_id, $request->all(), $user);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật Bộ Quy Tắc Siết Chặt Quản Lý Kho & Hạn Mức thành công.',
            'data' => $updatedRule,
        ]);
    }

    /**
     * Resolve Discrepancy Dispute & Assign Accountability
     */
    public function resolveDispute(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'responsible_type' => 'required|string|in:warehouse_staff,transporter,branch_staff,unknown',
            'responsible_user_id' => ['nullable', TenantRule::exists('users')],
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $user = $request->user();

        try {
            $dispute = $this->governanceService->resolveDispute(
                $id,
                $user->restaurant_id,
                $user,
                $request->responsible_type,
                $request->responsible_user_id,
                $request->resolution_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã xử lý biên bản bất đồng & quy trách nhiệm tài chính.',
                'data' => $dispute,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function respondDispute(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['response' => 'required|string|max:2000']);
        $user = $request->user();

        try {
            $dispute = $this->governanceService->respondToDispute(
                $id,
                $user->restaurant_id,
                $user,
                $data['response'],
            );

            return response()->json(['success' => true, 'message' => 'Đã ghi nhận ý kiến phản hồi của bạn.', 'data' => $dispute]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
