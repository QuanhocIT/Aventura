<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SupplyRequestReceivingReport;
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
        $receivingReports = SupplyRequestReceivingReport::where('restaurant_id', $user->restaurant_id)
            ->whereIn('status', [
                SupplyRequestReceivingReport::STATUS_CONFIRMED_PENDING_ACK,
                SupplyRequestReceivingReport::STATUS_DRIVER_CONFIRMED,
                SupplyRequestReceivingReport::STATUS_RESOLVED,
            ])
            ->with([
                'items.ingredient.unit',
                'supplyRequest.toBranch',
                'supplyRequest.transporter',
                'transporter',
                'confirmedBy',
                'driverConfirmedBy',
                'reviewedBy',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Inertia::render('inventory/WarehouseGovernance', [
            'summary' => $summary,
            'rules' => $summary['rules'],
            'recentDisputes' => $summary['recent_disputes'],
            'employees' => $employees,
            'receivingReports' => $receivingReports,
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
            'penalty_amount' => 'nullable|numeric|min:0',
            'write_off_inventory' => 'nullable|boolean',
            'claim_status' => 'nullable|string|in:pending_collection,collected,waived',
        ]);

        $user = $request->user();

        try {
            $dispute = $this->governanceService->resolveDispute(
                $id,
                $user->restaurant_id,
                $user,
                $request->responsible_type,
                $request->responsible_user_id ? (int) $request->responsible_user_id : null,
                $request->resolution_notes,
                $request->filled('penalty_amount') ? (float) $request->penalty_amount : null,
                (bool) $request->input('write_off_inventory', false),
                $request->input('claim_status')
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

    /**
     * Collect compensation claim from transporter.
     */
    public function collectClaim(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'collected_amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:cash,bank',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        try {
            $dispute = $this->governanceService->collectTransporterClaim(
                $id,
                $user->restaurant_id,
                $user,
                (float) $request->collected_amount,
                $request->payment_method,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Đã ghi nhận thu tiền bồi thường từ đơn vị vận chuyển và hạch toán kế toán thành công.',
                'data' => $dispute,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export disputes report to CSV.
     */
    public function exportDisputesReport(Request $request)
    {
        $user = $request->user();
        $disputes = \App\Models\InventoryDiscrepancyDispute::where('restaurant_id', $user->restaurant_id)
            ->with([
                'ingredient.unit',
                'responsibleUser',
                'resolver',
                'supplyRequest.toBranch',
                'supplyRequest.fromBranch',
                'supplyRequest.transporter',
            ])
            ->orderByDesc('id')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bao-cao-bat-dong-kho-'.now()->format('Ymd-His').'.csv"',
        ];

        $callback = function () use ($disputes) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 for Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Mã Biên Bản',
                'Ngày Ghi Nhận',
                'Mã Đơn Cấp Phát',
                'Chi Nhánh Nhận',
                'Mặt Hàng',
                'ĐVT',
                'SL Thực Xuất',
                'SL Thực Nhận',
                'SL Chênh Lệch',
                'Tổng Thiệt Hại (VNĐ)',
                'Bên Chịu Trách Nhiệm',
                'Người Chịu Phạt',
                'Số Tiền Phạt Lương (VNĐ)',
                'Công Ty Hỗ Trợ/Miễn (VNĐ)',
                'Trạng Thái',
                'Hạch Toán Kho',
                'Ghi Chú Kết Luận',
                'Người Giải Quyết',
                'Ngày Giải Quyết',
            ]);

            foreach ($disputes as $d) {
                $respLabel = match ($d->responsible_type) {
                    'warehouse_staff' => 'Nhân viên Kho Tổng',
                    'branch_staff' => 'Nhân viên Chi nhánh',
                    'transporter' => 'Đơn vị Vận chuyển',
                    default => 'Chưa xác định',
                };

                $statusLabel = match ($d->status) {
                    'open' => 'Mới phát sinh',
                    'investigating' => 'Đang đối soát',
                    'appealed' => 'Khiếu nại/Phản hồi',
                    'penalized' => 'Đã phạt lương',
                    'resolved' => 'Đã xử lý xong',
                    default => $d->status,
                };

                fputcsv($handle, [
                    $d->dispute_code,
                    $d->created_at?->format('d/m/Y H:i') ?? '',
                    $d->supplyRequest?->request_code ?? '',
                    $d->supplyRequest?->toBranch?->name ?? '',
                    $d->ingredient?->name ?? '',
                    $d->ingredient?->unit?->symbol ?? '',
                    (float) $d->dispatched_quantity,
                    (float) $d->received_quantity,
                    (float) $d->discrepancy_quantity,
                    (float) $d->financial_loss_amount,
                    $respLabel,
                    $d->responsibleUser?->name ?? ($d->responsible_type === 'transporter' ? ($d->supplyRequest?->transporter?->name ?? 'Tài xế/ĐVVC') : ''),
                    (float) ($d->penalty_amount ?? 0),
                    (float) ($d->waived_amount ?? 0),
                    $statusLabel,
                    $d->write_off_transaction_id ? 'Đã xuất hao hụt' : 'Chưa hạch toán',
                    $d->resolution_notes ?? '',
                    $d->resolver?->name ?? '',
                    $d->resolved_at?->format('d/m/Y H:i') ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
