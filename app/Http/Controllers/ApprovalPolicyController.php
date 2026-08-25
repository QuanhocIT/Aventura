<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalPolicy;
use App\Models\AuditLog;
use App\Models\RestaurantBranch;
use App\Models\User;
use App\Support\ApprovalOperations;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ma trận thẩm quyền — Chủ doanh nghiệp quyết định Quản lý được duyệt gì.
 */
class ApprovalPolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = (int) $user->restaurant_id;

        // Nhà hàng cũ chưa có chính sách thì nạp bộ mặc định để hệ thống không bị khóa toàn bộ.
        if (! ApprovalPolicy::where('restaurant_id', $restaurantId)->exists()) {
            ApprovalPolicyDefaults::applyTo($restaurantId);
        }

        $policies = ApprovalPolicy::where('restaurant_id', $restaurantId)
            ->with(['branch:id,name', 'updatedBy:id,name'])
            ->get()
            ->map(fn (ApprovalPolicy $policy) => [
                'id' => $policy->id,
                'operation_type' => $policy->operation_type,
                'operation_label' => ApprovalOperations::label($policy->operation_type),
                'branch_id' => $policy->branch_id,
                'branch_name' => $policy->branch?->name,
                'manager_can_approve' => $policy->manager_can_approve,
                'manager_limit_amount' => $policy->manager_limit_amount !== null ? (float) $policy->manager_limit_amount : null,
                'manager_daily_limit' => $policy->manager_daily_limit !== null ? (float) $policy->manager_daily_limit : null,
                'manager_monthly_limit' => $policy->manager_monthly_limit !== null ? (float) $policy->manager_monthly_limit : null,
                'requires_owner_countersign' => $policy->requires_owner_countersign,
                'auto_escalate_after_minutes' => $policy->auto_escalate_after_minutes,
                'conditions' => $policy->conditions,
                'is_active' => $policy->is_active,
                'updated_at' => $policy->updated_at?->toIso8601String(),
                'updated_by_name' => $policy->updatedBy?->name,
            ])
            ->values();

        $policyCollection = $policies->collect();
        $chainPolicies = $policyCollection->whereNull('branch_id');
        $summary = [
            'total' => $chainPolicies->count(),
            'active' => $chainPolicies->where('is_active', true)->count(),
            'manager_enabled' => $chainPolicies->where('manager_can_approve', true)->count(),
            'branch_overrides' => $policyCollection->whereNotNull('branch_id')->count(),
            'countersign' => $chainPolicies->where('requires_owner_countersign', true)->count(),
            'auto_escalation' => $chainPolicies->whereNotNull('auto_escalate_after_minutes')->count(),
            'forbidden' => count(ApprovalOperations::MANAGER_FORBIDDEN),
            'last_updated_at' => $policyCollection->pluck('updated_at')->filter()->sortDesc()->first(),
        ];

        $delegations = ApprovalDelegation::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->with(['delegator:id,name', 'delegatee:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('end_date')
            ->get()
            ->map(fn (ApprovalDelegation $delegation) => [
                'id' => $delegation->id,
                'delegator_id' => $delegation->delegator_id,
                'delegator_name' => $delegation->delegator?->name,
                'delegatee_id' => $delegation->delegatee_id,
                'delegatee_name' => $delegation->delegatee?->name,
                'module' => $delegation->module,
                'max_amount_limit' => $delegation->max_amount_limit !== null ? (float) $delegation->max_amount_limit : null,
                'start_date' => $delegation->start_date?->format('Y-m-d'),
                'end_date' => $delegation->end_date?->format('Y-m-d'),
                'is_active' => $delegation->is_active,
                'is_valid_now' => $delegation->isValidForNow(),
                'reason' => $delegation->reason,
            ])
            ->values();

        return Inertia::render('approvals/Policies', [
            'policies' => $policies,
            'summary' => $summary,
            'branches' => RestaurantBranch::where('restaurant_id', $restaurantId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'delegations' => $delegations,
            'eligibleManagers' => User::where('restaurant_id', $restaurantId)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['manager', 'quản lý', 'quan_ly', 'quanly']))
                ->select('id', 'name', 'branch_id')
                ->orderBy('name')
                ->get(),
            'delegationModules' => [
                ['value' => 'all', 'label' => 'Toàn bộ luồng phê duyệt'],
                ['value' => 'inventory', 'label' => 'Kho & tồn'],
                ['value' => 'supply_request', 'label' => 'Cung ứng'],
                ['value' => 'expense', 'label' => 'Chi phí & lương'],
                ['value' => 'audit', 'label' => 'Đối soát & kiểm tra'],
            ],
            'forbiddenForManager' => collect(ApprovalOperations::MANAGER_FORBIDDEN)
                ->map(fn (string $operationType) => [
                    'operation_type' => $operationType,
                    'operation_label' => ApprovalOperations::label($operationType),
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = (int) $user->restaurant_id;
        $data = $request->validate([
            'policies' => ['required', 'array', 'max:200'],
            'policies.*.operation_type' => ['required', 'string', Rule::in(ApprovalOperations::all())],
            'policies.*.branch_id' => ['nullable', 'integer', Rule::exists('restaurant_branches', 'id')->where('restaurant_id', $restaurantId)],
            'policies.*.manager_can_approve' => ['required', 'boolean'],
            'policies.*.manager_limit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'policies.*.manager_daily_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'policies.*.manager_monthly_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'policies.*.requires_owner_countersign' => ['required', 'boolean'],
            'policies.*.auto_escalate_after_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'policies.*.is_active' => ['required', 'boolean'],
        ]);

        $seenScopes = [];
        foreach ($data['policies'] as $index => $row) {
            $scope = $row['operation_type'].'|'.($row['branch_id'] ?? 'chain');
            if (isset($seenScopes[$scope])) {
                throw ValidationException::withMessages([
                    "policies.{$index}.operation_type" => 'Một thao tác chỉ được có một cấu hình trong cùng phạm vi.',
                ]);
            }
            $seenScopes[$scope] = true;

            if ($row['manager_can_approve']
                && $row['manager_limit_amount'] !== null
                && $row['manager_daily_limit'] !== null
                && (float) $row['manager_daily_limit'] < (float) $row['manager_limit_amount']) {
                throw ValidationException::withMessages([
                    "policies.{$index}.manager_daily_limit" => 'Hạn mức ngày không được thấp hơn hạn mức mỗi lần.',
                ]);
            }

            if ($row['manager_can_approve']
                && $row['manager_daily_limit'] !== null
                && $row['manager_monthly_limit'] !== null
                && (float) $row['manager_monthly_limit'] < (float) $row['manager_daily_limit']) {
                throw ValidationException::withMessages([
                    "policies.{$index}.manager_monthly_limit" => 'Hạn mức tháng không được thấp hơn hạn mức ngày.',
                ]);
            }
        }

        $changedPolicies = [];
        DB::transaction(function () use ($data, $restaurantId, $user, &$changedPolicies): void {
            foreach ($data['policies'] as $row) {
                // Chặn cứng ở tầng ghi: các thao tác nhạy cảm không thể bật cho Quản lý,
                // kể cả khi payload bị sửa thủ công.
                $canApprove = ApprovalOperations::isForbiddenForManager($row['operation_type'])
                    ? false
                    : (bool) $row['manager_can_approve'];

                $scope = [
                    'restaurant_id' => $restaurantId,
                    'operation_type' => $row['operation_type'],
                    'branch_id' => $row['branch_id'] ?? null,
                ];
                $existing = ApprovalPolicy::withoutGlobalScopes()->where($scope)->first();
                $nextValues = [
                    'manager_can_approve' => $canApprove,
                    'manager_limit_amount' => $canApprove ? ($row['manager_limit_amount'] ?? null) : null,
                    'manager_daily_limit' => $canApprove ? ($row['manager_daily_limit'] ?? null) : null,
                    'manager_monthly_limit' => $canApprove ? ($row['manager_monthly_limit'] ?? null) : null,
                    'requires_owner_countersign' => $canApprove && (bool) $row['requires_owner_countersign'],
                    'auto_escalate_after_minutes' => $canApprove ? ($row['auto_escalate_after_minutes'] ?? null) : null,
                    'is_active' => (bool) $row['is_active'],
                    'updated_by' => $user->id,
                ];

                $before = $existing?->only(array_keys($nextValues));
                if (! $existing || array_diff_assoc($nextValues, $before ?? [])) {
                    $changedPolicies[] = [
                        'operation_type' => $row['operation_type'],
                        'branch_id' => $row['branch_id'] ?? null,
                        'before' => $before,
                        'after' => $nextValues,
                    ];
                }

                ApprovalPolicy::withoutGlobalScopes()->updateOrCreate($scope, $nextValues);
            }
        });

        if ($changedPolicies !== []) {
            AuditLog::log('approval_policy_updated', 'updated', null, null, ['changed_policies' => $changedPolicies]);
        }

        return back()->with('success', 'Đã cập nhật ma trận thẩm quyền.');
    }

    public function storeDelegation(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);

        $restaurantId = (int) $user->restaurant_id;
        $data = $request->validate([
            'delegatee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('restaurant_id', $restaurantId)],
            'module' => ['required', Rule::in(['all', 'inventory', 'supply_request', 'expense', 'audit'])],
            'max_amount_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $delegatee = User::where('restaurant_id', $restaurantId)->findOrFail($data['delegatee_id']);
        abort_unless($delegatee->isBranchManager(), 422, 'Chỉ có thể ủy quyền cho tài khoản Quản lý chi nhánh.');
        abort_if((int) $delegatee->id === (int) $user->id, 422, 'Không thể ủy quyền cho chính tài khoản đang thao tác.');

        $delegation = ApprovalDelegation::create([
            'restaurant_id' => $restaurantId,
            'delegator_id' => $user->id,
            'delegatee_id' => $delegatee->id,
            'module' => $data['module'],
            'max_amount_limit' => $data['max_amount_limit'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_active' => true,
            'reason' => $data['reason'] ?? null,
        ]);

        AuditLog::log('approval_delegation_created', 'created', $delegation, null, $delegation->only([
            'delegatee_id', 'module', 'max_amount_limit', 'start_date', 'end_date', 'reason',
        ]));

        return back()->with('success', 'Đã tạo ủy quyền phê duyệt có thời hạn.');
    }

    public function destroy(Request $request, ApprovalPolicy $policy): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_unless((int) $policy->restaurant_id === (int) $user->restaurant_id, 404);
        abort_if($policy->branch_id === null, 422, 'Không thể xóa chính sách toàn chuỗi.');

        $before = $policy->only([
            'operation_type', 'branch_id', 'manager_can_approve', 'manager_limit_amount',
            'manager_daily_limit', 'manager_monthly_limit', 'requires_owner_countersign',
            'auto_escalate_after_minutes', 'is_active',
        ]);
        $policy->delete();
        AuditLog::log('approval_policy_branch_override_deleted', 'deleted', $policy, $before, null);

        return back()->with('success', 'Đã xóa cấu hình ghi đè; chi nhánh sẽ dùng chính sách toàn chuỗi.');
    }

    public function destroyDelegation(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isOwner() || $user->isSuperAdmin(), 403);
        abort_unless((int) $delegation->restaurant_id === (int) $user->restaurant_id, 404);

        $delegation->update(['is_active' => false]);
        AuditLog::log('approval_delegation_revoked', 'updated', $delegation, ['is_active' => true], ['is_active' => false]);

        return back()->with('success', 'Đã thu hồi ủy quyền.');
    }
}
