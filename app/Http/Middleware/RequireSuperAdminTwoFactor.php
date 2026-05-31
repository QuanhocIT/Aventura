<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminTwoFactor
{
    /**
     * Bắt buộc tài khoản có role super_admin phải kích hoạt 2FA mới được vào Dashboard và các phân hệ quản lý.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Chỉ áp dụng kiểm tra đối với tài khoản Super Admin
        if ($user && $user->isSuperAdmin()) {
            // 2. Nếu Super Admin chưa hoàn thành/xác thực mã bảo mật 2FA
            if (is_null($user->two_factor_confirmed_at)) {
                
                // 3. Cho phép bỏ qua đối với trang bảo mật (security settings), các route Fortify 2FA, và đăng xuất
                if ($request->routeIs('security.edit')
                    || $request->routeIs('logout')
                    || $request->is('logout')
                    || Str::contains($request->path(), 'two-factor')
                ) {
                    return $next($request);
                }

                // 4. Chặn đứng truy cập và chuyển hướng về trang bảo mật bắt buộc cấu hình 2FA
                return redirect()->route('security.edit')
                    ->with('error', 'Tài khoản Super Admin bắt buộc phải kích hoạt xác thực 2FA để tiếp tục truy cập các phân hệ khác của nền tảng.');
            }
        }

        return $next($request);
    }
}
