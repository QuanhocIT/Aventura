<?php

namespace App\Http\Controllers;

use App\Models\CompanyPolicy;
use App\Models\OperationalInfringementReport;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationalAuditController extends Controller
{
    /**
     * Operational Audit & Penalty Approval Page (Inertia View for Inspector & Owner)
     */
    public function page(Request $request): Response
    {
        $user = $request->user();
        $isOwnerOrSuperAdmin = $user->isOwner() || $user->isSuperAdmin();

        $reports = OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)
            ->with(['branch', 'inspector:id,name,email', 'policy:id,title,policy_code', 'offender:id,name,email', 'approver:id,name'])
            ->orderByDesc('id')
            ->get();

        $policies = CompanyPolicy::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'published')
            ->get();

        $branches = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $employees = User::where('restaurant_id', $user->restaurant_id)
            ->select('id', 'name', 'email', 'branch_id')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [
                'supplier',
                'owner',
                'operations_inspector',
            ]))
            ->get();

        return Inertia::render('operations/OperationalAudit', [
            'reports' => $reports,
            'policies' => $policies,
            'branches' => $branches,
            'employees' => $employees,
            'isOwner' => $isOwnerOrSuperAdmin,
            'isInspector' => $isOwnerOrSuperAdmin || $user->can('operational_audit.report'),
        ]);
    }

    /**
     * API: Inspector files penalty report
     */
    public function storeReport(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', TenantRule::exists('restaurant_branches')],
            'policy_id' => ['nullable', TenantRule::exists('company_policies')],
            'offender_user_id' => ['nullable', TenantRule::exists('users')],
            'infringement_date' => 'required|date',
            'description' => 'required|string|max:2000',
            'proof_photo_url' => 'nullable|string',
            'proof_photo' => 'nullable|file|image|max:5120',
            'penalty_amount' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        if ($request->filled('policy_id')) {
            $policy = CompanyPolicy::where('restaurant_id', $user->restaurant_id)
                ->where('status', 'published')
                ->findOrFail($request->policy_id);

            $appliesToBranch = $policy->applies_to_all_branches
                || in_array((int) $request->branch_id, array_map('intval', $policy->applicable_branch_ids ?? []), true);

            if (! $appliesToBranch) {
                throw ValidationException::withMessages([
                    'policy_id' => 'Quy định này không áp dụng cho chi nhánh đang thanh tra.',
                ]);
            }
        }

        if ($request->filled('offender_user_id')) {
            $offender = User::where('restaurant_id', $user->restaurant_id)->findOrFail($request->offender_user_id);
            $offenderBranchId = $offender->assignedBranchId();

            if ($offenderBranchId === null || $offenderBranchId !== (int) $request->branch_id) {
                throw ValidationException::withMessages([
                    'offender_user_id' => 'Nhân sự vi phạm không thuộc chi nhánh đang thanh tra.',
                ]);
            }
        }

        $proofPhotoUrl = $request->input('proof_photo_url');
        if ($request->hasFile('proof_photo')) {
            $path = $request->file('proof_photo')->store('audit-proofs', 'local');
            $proofPhotoUrl = route('secure-files.download', ['path' => $path]);
        }

        $reportCode = 'INF-'.Carbon::now()->format('Ymd').'-'.str_pad((string) (OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)->count() + 1), 4, '0', STR_PAD_LEFT);

        $report = OperationalInfringementReport::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id' => $request->branch_id,
            'report_code' => $reportCode,
            'inspector_id' => $user->id,
            'policy_id' => $request->policy_id,
            'offender_user_id' => $request->offender_user_id,
            'infringement_date' => $request->infringement_date,
            'description' => $request->description,
            'proof_photo_url' => $proofPhotoUrl,
            'penalty_amount' => $request->penalty_amount,
            'status' => 'pending_owner_approval',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lập Biên bản Vi phạm & gửi trình Chủ doanh nghiệp phê duyệt.',
            'data' => $report->load(['branch', 'policy', 'offender']),
        ]);
    }

    /**
     * API: Owner approves penalty report
     */
    public function approveReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isOwner() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Chỉ Chủ doanh nghiệp mới có quyền phê duyệt biên bản phạt.'], 403);
        }

        $report = OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        if ($report->status !== 'pending_owner_approval') {
            return response()->json(['success' => false, 'message' => 'Biên bản này đã được xử lý trước đó.'], 422);
        }

        $report->update([
            'status' => 'approved',
            'owner_notes' => $request->input('owner_notes'),
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã phê duyệt Biên bản vi phạm và ghi nhận mức phạt tài chính.',
            'data' => $report->fresh(['branch', 'policy', 'offender', 'approver']),
        ]);
    }

    /**
     * API: Owner rejects penalty report
     */
    public function rejectReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isOwner() || $user->isSuperAdmin())) {
            return response()->json(['success' => false, 'message' => 'Chỉ Chủ doanh nghiệp mới có quyền từ chối biên bản phạt.'], 403);
        }

        $report = OperationalInfringementReport::where('restaurant_id', $user->restaurant_id)->findOrFail($id);

        if ($report->status !== 'pending_owner_approval') {
            return response()->json(['success' => false, 'message' => 'Biên bản này đã được xử lý trước đó.'], 422);
        }

        $report->update([
            'status' => 'rejected',
            'owner_notes' => $request->input('owner_notes'),
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối biên bản phạt.',
            'data' => $report->fresh(['branch', 'policy', 'offender', 'approver']),
        ]);
    }
}
