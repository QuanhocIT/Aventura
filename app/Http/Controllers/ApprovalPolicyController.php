<?php

namespace App\Http\Controllers;

use App\Models\ApprovalPolicy;
use App\Models\RestaurantBranch;
use App\Support\ApprovalOperations;
use App\Support\ApprovalPolicyDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        // Nhà hàng cũ chưa có chính sách nào thì nạp bộ mặc định, nếu không màn
        // hình sẽ trống và Quản lý không duyệt được gì cả.
        if (! ApprovalPolicy::where('restaurant_id', $restaurantId)->exists()) {
            ApprovalPolicyDefaults::applyTo($restaurantId);
        }

        $policies = ApprovalPolicy::where('restaurant_id', $restaurantId)
            ->with('branch:id,name')
            ->get()
            ->map(fn (ApprovalPolicy $p) => [
                'id' => $p->id,
                'operation_type' => $p->operation_type,
                'operation_label' => ApprovalOperations::label($p->operation_type),
                'branch_id' => $p->branch_id,
                'branch_name' => $p->branch?->name,
                'manager_can_approve' => $p->manager_can_approve,
                'manager_limit_amount' => $p->manager_limit_amount !== null ? (float) $p->manager_limit_amount : null,
                'manager_daily_limit' => $p->manager_daily_limit !== null ? (float) $p->manager_daily_limit : null,
                'manager_monthly_limit' => $p->manager_monthly_limit !== null ? (float) $p->manager_monthly_limit : null,
                'requires_owner_countersign' => $p->requires_owner_countersign,
                'conditions' => $p->conditions,
                'is_active' => $p->is_active,
            ])
            ->values();

        return Inertia::render('approvals/Policies', [
            'policies' => $policies,
            'branches' => RestaurantBranch::where('restaurant_id', $restaurantId)
                ->select('id', 'name')->orderBy('name')->get(),
            // Danh sách cấm tuyệt đối, hiển thị để Chủ biết ranh giới cứng của
            // hệ thống — không có công tắc nào bật được các mục này.
            'forbiddenForManager' => collect(ApprovalOperations::MANAGER_FORBIDDEN)
                ->map(fn (string $op) => [
                    'operation_type' => $op,
                    'operation_label' => ApprovalOperations::label($op),
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
            'policies.*.is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $restaurantId, $user): void {
            foreach ($data['policies'] as $row) {
                // Chặn cứng ở tầng ghi: kể cả khi payload bị sửa thủ công, các
                // thao tác trong danh sách cấm vẫn không thể bật cho Quản lý.
                $canApprove = ApprovalOperations::isForbiddenForManager($row['operation_type'])
                    ? false
                    : (bool) $row['manager_can_approve'];

                ApprovalPolicy::withoutGlobalScopes()->updateOrCreate(
                    [
                        'restaurant_id' => $restaurantId,
                        'operation_type' => $row['operation_type'],
                        'branch_id' => $row['branch_id'] ?? null,
                    ],
                    [
                        'manager_can_approve' => $canApprove,
                        'manager_limit_amount' => $row['manager_limit_amount'] ?? null,
                        'manager_daily_limit' => $row['manager_daily_limit'] ?? null,
                        'manager_monthly_limit' => $row['manager_monthly_limit'] ?? null,
                        'requires_owner_countersign' => (bool) $row['requires_owner_countersign'],
                        'is_active' => (bool) $row['is_active'],
                        'updated_by' => $user->id,
                    ],
                );
            }
        });

        return back()->with('success', 'Đã cập nhật ma trận thẩm quyền.');
    }
}
