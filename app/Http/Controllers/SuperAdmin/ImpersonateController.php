<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // 1. Chỉ Super Admin mới được bắt đầu sắm vai
        if (!$currentUser || !$currentUser->isSuperAdmin()) {
            abort(403, 'Chỉ tài khoản Super Admin mới được phép thực hiện hành động này.');
        }

        // 2. Không được sắm vai chính mình
        if ($currentUser->id === $user->id) {
            return back()->with('error', 'Bạn không thể sắm vai chính tài khoản của mình.');
        }

        // 3. Không được sắm vai một Super Admin khác để tránh leo thang đặc quyền trái phép
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Không thể sắm vai một tài khoản Super Admin khác.');
        }

        // 4. Lưu lại ID của Super Admin hiện tại vào Session trước khi chuyển vai
        $request->session()->put('impersonate_original_user_id', $currentUser->id);

        // 5. Đăng nhập dưới danh nghĩa User mục tiêu
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "Bạn đã đăng nhập sắm vai thành công dưới danh nghĩa \"{$user->name}\".");
    }

    public function stop(Request $request): RedirectResponse
    {
        // 1. Kiểm tra xem session có chứa khóa sắm vai gốc hay không
        if (!$request->session()->has('impersonate_original_user_id')) {
            abort(403, 'Bạn không ở trong chế độ đăng nhập sắm vai.');
        }

        $originalAdminId = $request->session()->get('impersonate_original_user_id');
        $originalAdmin = User::findOrFail($originalAdminId);

        // 2. Đăng nhập lại tài khoản Super Admin gốc
        Auth::login($originalAdmin);

        // 3. Xóa thông tin sắm vai khỏi Session
        $request->session()->forget('impersonate_original_user_id');

        return redirect()->route('superadmin.accounts.index')->with('success', 'Đã thoát chế độ sắm vai và quay lại tài khoản Super Admin.');
    }
}
