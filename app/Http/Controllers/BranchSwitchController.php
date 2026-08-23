<?php

namespace App\Http\Controllers;

use App\Models\RestaurantBranch;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function switchBranch(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string', 'in:all,branch'],
        ]);

        $user = $request->user();
        if ($user->hasRole('warehouse_manager') && ! $user->isOwner() && ! $user->isSuperAdmin()) {
            abort(403, 'Tài khoản Trưởng kho Tổng được cố định phạm vi tại Kho Tổng, không thể chuyển đổi chi nhánh.');
        }

        if (! $user->canViewAllBranches() && ! $user->isBranchManager()) {
            abort(403, 'Bạn không có quyền chuyển đổi chi nhánh.');
        }

        $isAllBranches = ($data['scope'] ?? null) === 'all'
            || (array_key_exists('branch_id', $data) && $data['branch_id'] === null);

        if ($isAllBranches) {
            if (! $user->canViewAllBranches()) {
                abort(403, 'Chỉ chủ nhà hàng mới có thể xem toàn bộ chi nhánh.');
            }

            session()->forget('active_branch_id');
            session(['active_branch_scope' => 'all']);

            return back()->with('success', 'Đã chuyển sang phạm vi toàn chuỗi.');
        }

        if (! isset($data['branch_id'])) {
            abort(422, 'Vui lòng chọn chi nhánh hoặc phạm vi toàn chuỗi.');
        }

        $branchId = (int) $data['branch_id'];
        $branchQuery = RestaurantBranch::query()
            ->whereKey($branchId)
            ->where('status', 'active');

        if (! $user->isSuperAdmin()) {
            $branchQuery->where('restaurant_id', $user->restaurant_id);
        }

        if (! $branchQuery->exists()) {
            abort(403, 'Chi nhánh không thuộc nhà hàng hoặc đang tạm ngưng.');
        }

        if (! $user->canAccessBranch($branchId)) {
            abort(403, 'Bạn chỉ có thể xem chi nhánh được phân công.');
        }

        session([
            'active_branch_id' => $branchId,
            'active_branch_scope' => 'branch',
        ]);

        return back()->with('success', 'Chuyển đổi chi nhánh làm việc thành công!');
    }
}
