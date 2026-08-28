<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthorityDeniedException;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Services\ApprovalAuthorityService;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalService $approvalService,
        private ApprovalAuthorityService $authorityService,
    ) {}

    /**
     * Hàng chờ phê duyệt.
     *
     * Trước đây màn hình này chỉ mở cho Chủ, trong khi route approve/reject lại
     * cho phép Quản lý — nên Quản lý có quyền mà không có nơi thực hiện.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canReview($user), 403);

        $restaurantId = $user->restaurant_id;
        $this->approvalService->autoEscalateOverdue((int) $restaurantId);
        $statusFilter = $request->input('status', 'open');
        $seesAllBranches = $user->isOwner() || $user->isSuperAdmin();

        $query = ApprovalRequest::forRestaurant($restaurantId)
            ->with(['requester:id,name', 'reviewer:id,name', 'branch:id,name'])
            ->latest();

        if (! $seesAllBranches) {
            $query->forBranches($this->authorityService->managedBranchIds($user));
        }

        match ($statusFilter) {
            'all' => null,
            'open' => $query->open(),
            'overdue' => $query->open()->where('created_at', '<=', now()->subHours(24)),
            default => $query->where('status', $statusFilter),
        };

        $approvals = $query->limit(200)->get()->map(function (ApprovalRequest $a) use ($user): array {
            // Tính sẵn thẩm quyền để giao diện khóa nút kèm lý do, thay vì để
            // người dùng bấm rồi mới nhận 403.
            $decision = $this->authorityService->decide($user, $a);

            return [
                'id' => $a->id,
                'operation_type' => $a->operation_type,
                'operation_label' => $a->operationLabel(),
                'operation_data' => $a->operation_data,
                'status' => $a->status,
                'branch_name' => $a->branch?->name,
                'amount_involved' => $a->amount_involved ? (float) $a->amount_involved : null,
                'required_authority' => $a->required_authority,
                'requester_name' => $a->requester?->name ?? '—',
                'reviewer_name' => $a->reviewer?->name ?? null,
                'reviewer_role' => $a->decided_by_role,
                'rejection_reason' => $a->rejection_reason,
                'escalation_reason' => $a->escalation_reason,
                'reviewed_at' => $a->reviewed_at?->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
                'can_decide' => $decision->allowed,
                'block_reason' => $decision->reason,
            ];
        });

        $statsQuery = fn () => ApprovalRequest::forRestaurant($restaurantId)
            ->when(! $seesAllBranches, fn ($q) => $q->forBranches($this->authorityService->managedBranchIds($user)));

        return Inertia::render('approvals/Index', [
            'approvals' => $approvals,
            'stats' => [
                'pending' => (clone $statsQuery())->where('status', ApprovalRequest::STATUS_PENDING)->count(),
                'escalated' => (clone $statsQuery())->where('status', ApprovalRequest::STATUS_ESCALATED)->count(),
                'approved_today' => (clone $statsQuery())->where('status', ApprovalRequest::STATUS_APPROVED)->whereDate('reviewed_at', today())->count(),
                'rejected_today' => (clone $statsQuery())->where('status', ApprovalRequest::STATUS_REJECTED)->whereDate('reviewed_at', today())->count(),
            ],
            'statusFilter' => $statusFilter,
            'viewerScope' => $seesAllBranches ? 'chain' : 'branch',
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canReview($user), 403);
        abort_if($approval->restaurant_id !== $user->restaurant_id, 403);

        try {
            $this->approvalService->approve($approval, $user);
        } catch (AuthorityDeniedException $e) {
            // Thiếu thẩm quyền là lỗi ủy quyền, không phải lỗi nhập liệu.
            abort(403, $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã phê duyệt yêu cầu.');
    }

    public function reject(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canReview($user), 403);
        abort_if($approval->restaurant_id !== $user->restaurant_id, 403);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $this->approvalService->reject($approval, $user, $data['rejection_reason']);
        } catch (AuthorityDeniedException $e) {
            abort(403, $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã từ chối yêu cầu.');
    }

    /**
     * Sổ phê duyệt — Chủ doanh nghiệp hậu kiểm các quyết định của Quản lý.
     */
    public function ledger(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = $user->restaurant_id;

        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'decided_by' => ['nullable', 'integer'],
            'operation_type' => ['nullable', 'string', 'max:50'],
            'unreviewed' => ['nullable', 'boolean'],
        ]);

        $decisions = ApprovalDecision::where('restaurant_id', $restaurantId)
            ->delegated()
            ->with(['branch:id,name', 'decidedBy:id,name', 'ownerReviewedBy:id,name'])
            ->when($filters['branch_id'] ?? null, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($filters['decided_by'] ?? null, fn ($q, $v) => $q->where('decided_by', $v))
            ->when($filters['operation_type'] ?? null, fn ($q, $v) => $q->where('operation_type', $v))
            ->when($filters['unreviewed'] ?? null, fn ($q) => $q->whereNull('owner_reviewed_at'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ApprovalDecision $d) => [
                'id' => $d->id,
                'approval_request_id' => $d->approval_request_id,
                'operation_type' => $d->operation_type,
                'operation_label' => $d->operationLabel(),
                'decision' => $d->decision,
                'amount_involved' => $d->amount_involved ? (float) $d->amount_involved : null,
                'decided_by_name' => $d->decided_by_name,
                'decided_by_role' => $d->decided_by_role,
                'branch_name' => $d->branch?->name,
                'reason' => $d->reason,
                'policy_snapshot' => $d->policy_snapshot,
                'ip_address' => $d->ip_address,
                'owner_reviewed_at' => $d->owner_reviewed_at?->format('H:i d/m/Y'),
                'owner_reviewed_by' => $d->ownerReviewedBy?->name,
                'created_at' => $d->created_at?->format('H:i d/m/Y'),
            ]);

        return Inertia::render('approvals/Ledger', [
            'decisions' => $decisions,
            'filters' => $filters,
            'summary' => $this->ledgerSummary($restaurantId),
            'branches' => RestaurantBranch::where('restaurant_id', $restaurantId)
                ->select('id', 'name')->orderBy('name')->get(),
            'managers' => User::where('restaurant_id', $restaurantId)
                ->role('manager')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Chủ ghi nhận đã xem một quyết định ủy quyền.
     */
    public function acknowledge(Request $request, ApprovalDecision $decision): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_if($decision->restaurant_id !== $user->restaurant_id, 403);

        if ($decision->owner_reviewed_at === null) {
            $decision->update([
                'owner_reviewed_at' => now(),
                'owner_reviewed_by' => $user->id,
            ]);
        }

        return back()->with('success', 'Đã ghi nhận xem xét.');
    }

    public function batchApproveLowRisk(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canReview($user), 403);

        $restaurantId = $user->restaurant_id;
        $seesAllBranches = $user->isOwner() || $user->isSuperAdmin();

        $query = ApprovalRequest::forRestaurant($restaurantId)->open();
        if (! $seesAllBranches) {
            $query->forBranches($this->authorityService->managedBranchIds($user));
        }

        $requests = $query->get();

        $approvedCount = 0;
        $skippedCount = 0;

        foreach ($requests as $req) {
            $decision = $this->authorityService->decide($user, $req);
            if (! $decision->allowed) {
                $skippedCount++;

                continue;
            }

            // Low-risk operations check: amount_involved null or <= 100,000 VND
            $isLowRiskType = in_array($req->operation_type, [
                'discount_small',
                'leave_request_short',
                'ingredient_transfer_small',
                'menu_item_pause',
            ], true) || ($req->amount_involved !== null && (float) $req->amount_involved <= 100000);

            if (! $isLowRiskType) {
                $skippedCount++;

                continue;
            }

            try {
                $this->approvalService->approve($req, $user);
                $approvedCount++;
            } catch (\Throwable $e) {
                $skippedCount++;
            }
        }

        $message = "Đã duyệt hàng loạt {$approvedCount} yêu cầu rủi ro thấp.";
        if ($skippedCount > 0) {
            $message .= " Đã bỏ qua {$skippedCount} yêu cầu rủi ro cao/vượt thẩm quyền.";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'approved_count' => $approvedCount,
                'skipped_count' => $skippedCount,
            ]);
        }

        return back()->with('success', $message);
    }

    private function ledgerSummary(int $restaurantId): array
    {
        $base = fn () => ApprovalDecision::where('restaurant_id', $restaurantId)->delegated();

        return [
            'awaiting_review' => (clone $base())->whereNull('owner_reviewed_at')->count(),
            'this_month' => (clone $base())->where('created_at', '>=', now()->startOfMonth())->count(),
            'amount_this_month' => (float) (clone $base())
                ->where('decision', 'approved')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount_involved'),
        ];
    }

    private function canReview(User $user): bool
    {
        return $user->isOwner()
            || $user->isSuperAdmin()
            || $user->isBranchManager()
            || $user->can('approve_requests');
    }
}
