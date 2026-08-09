<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvalService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);

        $restaurantId = $request->user()->restaurant_id;
        $statusFilter = $request->input('status', 'pending');

        $query = ApprovalRequest::forRestaurant($restaurantId)
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->latest();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $approvals = $query->get()->map(fn (ApprovalRequest $a) => [
            'id' => $a->id,
            'operation_type' => $a->operation_type,
            'operation_label' => $a->operationLabel(),
            'operation_data' => $a->operation_data,
            'status' => $a->status,
            'requester_name' => $a->requester?->name ?? '—',
            'reviewer_name' => $a->reviewer?->name ?? null,
            'rejection_reason' => $a->rejection_reason,
            'reviewed_at' => $a->reviewed_at?->format('H:i d/m/Y'),
            'created_at' => $a->created_at->format('H:i d/m/Y'),
        ]);

        $stats = [
            'pending' => ApprovalRequest::forRestaurant($restaurantId)->where('status', 'pending')->count(),
            'approved_today' => ApprovalRequest::forRestaurant($restaurantId)->where('status', 'approved')->whereDate('reviewed_at', today())->count(),
            'rejected_today' => ApprovalRequest::forRestaurant($restaurantId)->where('status', 'rejected')->whereDate('reviewed_at', today())->count(),
        ];

        return Inertia::render('approvals/Index', [
            'approvals' => $approvals,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        abort_unless($request->user()->isOwner() || $request->user()->isSuperAdmin(), 403);
        abort_if($approval->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($approval->status === 'pending', 422);

        if ($approval->operation_type === 'order_refund') {
            abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ doanh nghiệp mới được duyệt hoàn tiền.');
        }

        // Self-Approval Prevention Check
        abort_if($approval->requester_id === $request->user()->id, 403, 'Bạn không thể tự phê duyệt yêu cầu của chính mình.');

        try {
            $this->approvalService->approve($approval, $request->user());

            return back()->with('success', 'Đã phê duyệt yêu cầu.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        abort_unless($request->user()->can('approve_requests') || $request->user()->hasAnyRole(['owner', 'manager']), 403);
        abort_if($approval->restaurant_id !== $request->user()->restaurant_id, 403);
        abort_unless($approval->status === 'pending', 422);

        if ($approval->operation_type === 'order_refund') {
            abort_unless($request->user()->hasRole('owner'), 403, 'Chỉ chủ doanh nghiệp mới được từ chối hoàn tiền.');
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $this->approvalService->reject($approval, $request->user(), $data['rejection_reason']);

        return back()->with('success', 'Đã từ chối yêu cầu.');
    }
}
