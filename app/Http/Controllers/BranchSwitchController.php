<?php

namespace App\Http\Controllers;

use App\Models\RestaurantBranch;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function switchBranch(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->hasAnyRole(['owner', 'manager'])) {
            abort(403, 'Bạn không có quyền chuyển đổi chi nhánh.');
        }

        $branchId = (int) $data['branch_id'];

        // Verify the branch belongs to the user's restaurant (unless super admin)
        if (! $user->isSuperAdmin()) {
            $exists = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
                ->where('id', $branchId)
                ->exists();
            if (! $exists) {
                abort(403, 'Chi nhánh không thuộc nhà hàng của bạn.');
            }
        }

        // TRƯỚC ĐÂY KHÔNG CÓ: manager (khác owner/super_admin) được phép chuyển
        // sang BẤT KỲ chi nhánh nào của nhà hàng, không chỉ chi nhánh mình được
        // phân công (employees.branch_id) — vô hiệu hoá mô hình "quản lý chỉ phụ
        // trách 1 chi nhánh" đã mô tả trong RBAC. Owner/super_admin không bị giới hạn.
        if (! $user->isSuperAdmin() && ! $user->hasRole('owner')) {
            $assignedBranchId = $user->employee?->branch_id;
            if ($assignedBranchId !== null && $assignedBranchId !== $branchId) {
                abort(403, 'Bạn chỉ có thể xem chi nhánh được phân công.');
            }
        }

        session(['active_branch_id' => $branchId]);

        return back()->with('success', 'Chuyển đổi chi nhánh làm việc thành công!');
    }
}
