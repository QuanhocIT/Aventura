<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

        AuditLog::create([
            'restaurant_id' => $user->restaurant_id,
            'branch_id'     => null,
            'user_id'       => $currentUser->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'impersonate_start',
            'subject_type'  => User::class,
            'subject_id'    => $user->id,
            'old_values'    => null,
            'new_values'    => ['target_user_email' => $user->email, 'target_user_name' => $user->name],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

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

        /** @var User $impersonatedUser */
        $impersonatedUser = $request->user();

        // 2. Đăng nhập lại tài khoản Super Admin gốc
        Auth::login($originalAdmin);

        // 3. Xóa thông tin sắm vai khỏi Session
        $request->session()->forget('impersonate_original_user_id');

        AuditLog::create([
            'restaurant_id' => $impersonatedUser?->restaurant_id,
            'branch_id'     => null,
            'user_id'       => $originalAdmin->id,
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'impersonate_stop',
            'subject_type'  => User::class,
            'subject_id'    => $impersonatedUser?->id,
            'old_values'    => null,
            'new_values'    => [
                'target_user_email' => $impersonatedUser?->email,
                'target_user_name'  => $impersonatedUser?->name,
            ],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return redirect()->route('superadmin.accounts.index')->with('success', 'Đã thoát chế độ sắm vai và quay lại tài khoản Super Admin.');
    }
}
