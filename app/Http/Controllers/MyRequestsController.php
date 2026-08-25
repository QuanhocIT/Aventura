<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Yêu cầu của tôi" — mở cho mọi vai trò.
 *
 * Trước đây nhân viên gửi yêu cầu xong chỉ nhận được một thông báo rồi trôi,
 * không có chỗ nào tra lại xem đã được duyệt chưa và ai duyệt.
 */
class MyRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $statusFilter = $request->input('status', 'all');

        $query = ApprovalRequest::where('restaurant_id', $user->restaurant_id)
            ->where('requester_id', $user->id)
            ->with(['reviewer:id,name', 'branch:id,name'])
            ->latest();

        match ($statusFilter) {
            'all' => null,
            'open' => $query->open(),
            default => $query->where('status', $statusFilter),
        };

        $requests = $query->paginate(25)->withQueryString()->through(fn (ApprovalRequest $a) => [
            'id' => $a->id,
            'operation_type' => $a->operation_type,
            'operation_label' => $a->operationLabel(),
            'operation_data' => $a->operation_data,
            'status' => $a->status,
            'branch_name' => $a->branch?->name,
            'amount_involved' => $a->amount_involved ? (float) $a->amount_involved : null,
            // Đúng yêu cầu: cho biết ai đã phê duyệt và với vai trò gì.
            'reviewer_name' => $a->reviewer?->name,
            'reviewer_role' => $a->decided_by_role,
            'rejection_reason' => $a->rejection_reason,
            'escalation_reason' => $a->escalation_reason,
            'reviewed_at' => $a->reviewed_at?->format('H:i d/m/Y'),
            'created_at' => $a->created_at->format('H:i d/m/Y'),
        ]);

        $counts = ApprovalRequest::where('restaurant_id', $user->restaurant_id)
            ->where('requester_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('approvals/MyRequests', [
            'requests' => $requests,
            'statusFilter' => $statusFilter,
            'stats' => [
                'pending' => (int) ($counts[ApprovalRequest::STATUS_PENDING] ?? 0),
                'escalated' => (int) ($counts[ApprovalRequest::STATUS_ESCALATED] ?? 0),
                'approved' => (int) ($counts[ApprovalRequest::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($counts[ApprovalRequest::STATUS_REJECTED] ?? 0),
            ],
        ]);
    }
}
