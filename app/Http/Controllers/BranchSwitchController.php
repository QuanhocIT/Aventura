<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RestaurantBranch;

class BranchSwitchController extends Controller
{
    public function switchBranch(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        if (!$user->isSuperAdmin() && !$user->hasAnyRole(['owner', 'manager'])) {
            abort(403, 'Bạn không có quyền chuyển đổi chi nhánh.');
        }

        $branchId = (int) $data['branch_id'];

        // Verify the branch belongs to the user's restaurant (unless super admin)
        if (!$user->isSuperAdmin()) {
            $exists = RestaurantBranch::where('restaurant_id', $user->restaurant_id)
                ->where('id', $branchId)
                ->exists();
            if (!$exists) {
                abort(403, 'Chi nhánh không thuộc nhà hàng của bạn.');
            }
        }

        session(['active_branch_id' => $branchId]);

        return back()->with('success', 'Chuyển đổi chi nhánh làm việc thành công!');
    }
}
