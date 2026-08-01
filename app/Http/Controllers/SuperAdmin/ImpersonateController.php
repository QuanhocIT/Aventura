<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdminAuditStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        // 1. Chỉ Super Admin mới được bắt đầu sắm vai
        if (! $currentUser || ! $currentUser->isSuperAdmin()) {
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

        // 4. Bắt buộc nhập lý do sắm vai (Tối thiểu 10 ký tự) hoặc Ticket ID
        $validated = $request->validate([
            'reason' => 'required|string|min:10',
            'ticket_id' => 'nullable|string|max:50',
        ], [
            'reason.required' => 'Bạn phải nhập lý do tại sao cần sắm vai tài khoản này.',
            'reason.min' => 'Lý do sắm vai phải có ít nhất 10 ký tự.',
        ]);

        // 5. Lưu lại ID của Super Admin hiện tại vào Session trước khi chuyển vai
        $request->session()->put('impersonate_original_user_id', $currentUser->id);
        $request->session()->put('impersonate_reason', $validated['reason']);
        $request->session()->put('impersonate_ticket_id', $validated['ticket_id'] ?? null);
        // Mặc định sắm vai ở chế độ CHỈ ĐỌC (Read-Only)
        $request->session()->forget('impersonate_write_until');

        // 6. Đăng nhập dưới danh nghĩa User mục tiêu
        Auth::login($user);

        SuperAdminAuditStream::record('impersonate_start', [
            'target_user_email' => $user->email,
            'target_user_name' => $user->name,
            'reason' => $validated['reason'],
            'ticket_id' => $validated['ticket_id'] ?? null,
            'mode' => 'read_only',
        ], User::class, $user->id, 'warning');

        if ($request->header('X-Inertia')) {
            $request->session()->flash('success', "Đã sắm vai thành công dưới danh nghĩa \"{$user->name}\" (Chế độ CHỈ ĐỌC Read-Only).");

            return Inertia::location(route('dashboard'));
        }

        return redirect()->route('dashboard')->with('success', "Đã sắm vai thành công dưới danh nghĩa \"{$user->name}\" (Chế độ CHỈ ĐỌC Read-Only).");
    }

    /**
     * Yêu cầu cấp quyền Write-Mode tạm thời trong 15 phút khi sắm vai.
     */
    public function requestWrite(Request $request): Response
    {
        if (! $request->session()->has('impersonate_original_user_id')) {
            abort(403, 'Bạn không ở trong chế độ đăng nhập sắm vai.');
        }

        $validated = $request->validate([
            'write_reason' => 'required|string|min:10',
        ], [
            'write_reason.required' => 'Bạn phải cung cấp lý do cần thao tác ghi dữ liệu.',
            'write_reason.min' => 'Lý do xin quyền ghi phải từ 10 ký tự trở lên.',
        ]);

        // Cấp quyền Ghi trong 15 phút
        $expiresAt = now()->addMinutes(15)->timestamp;
        $request->session()->put('impersonate_write_until', $expiresAt);

        /** @var User $currentUser */
        $currentUser = $request->user();

        SuperAdminAuditStream::record('impersonate_request_write', [
            'write_reason' => $validated['write_reason'],
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ], User::class, $currentUser?->id, 'warning');

        return back()->with('success', 'Đã cấp quyền Write-Mode thành công trong 15 phút. Mọi thao tác ghi dữ liệu sẽ được giám sát chặt chẽ.');
    }

    public function stop(Request $request): Response
    {
        // 1. Kiểm tra xem session có chứa khóa sắm vai gốc hay không
        if (! $request->session()->has('impersonate_original_user_id')) {
            abort(403, 'Bạn không ở trong chế độ đăng nhập sắm vai.');
        }

        $originalAdminId = $request->session()->get('impersonate_original_user_id');
        $originalAdmin = User::findOrFail($originalAdminId);

        /** @var User $impersonatedUser */
        $impersonatedUser = $request->user();

        // 2. Đăng nhập lại tài khoản Super Admin gốc
        Auth::login($originalAdmin);

        // 3. Xóa thông tin sắm vai khỏi Session
        $request->session()->forget([
            'impersonate_original_user_id',
            'impersonate_reason',
            'impersonate_ticket_id',
            'impersonate_write_until',
        ]);

        SuperAdminAuditStream::record('impersonate_stop', [
            'target_user_email' => $impersonatedUser?->email,
            'target_user_name' => $impersonatedUser?->name,
        ], User::class, $impersonatedUser?->id, 'info');

        if ($request->header('X-Inertia')) {
            $request->session()->flash('success', 'Đã thoát chế độ sắm vai và quay lại tài khoản Super Admin.');

            return Inertia::location(route('superadmin.accounts.index'));
        }

        return redirect()->route('superadmin.accounts.index')->with('success', 'Đã thoát chế độ sắm vai và quay lại tài khoản Super Admin.');
    }
}
