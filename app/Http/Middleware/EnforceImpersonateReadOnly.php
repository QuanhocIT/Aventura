<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceImpersonateReadOnly
{
    /**
     * Chặn các thao tác ghi (POST/PUT/PATCH/DELETE) khi SuperAdmin đang trong chế độ Impersonate,
     * trừ khi được cấp quyền Write-Mode tạm thời.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonate_original_user_id')) {
            // Cho phép các route thoát impersonate hoặc xin quyền ghi
            if ($request->routeIs('impersonate.stop') || $request->routeIs('impersonate.request_write')) {
                return $next($request);
            }

            $writeUntil = $request->session()->get('impersonate_write_until');
            $isWriteEnabled = $writeUntil && now()->timestamp < $writeUntil;

            // Nếu không phải HTTP Safe Method (GET, HEAD, OPTIONS) và không bật Write Mode -> Chặn
            if (! $request->isMethodSafe() && ! $isWriteEnabled) {
                $message = 'Tài khoản SuperAdmin đang sắm vai ở chế độ CHỈ ĐỌC (Read-Only). Bạn không thể thay đổi dữ liệu của nhà hàng ngoại trừ khi yêu cầu cấp quyền Ghi (Write-Mode).';

                if ($request->header('X-Inertia') || $request->wantsJson()) {
                    return back()->with('error', $message);
                }

                return redirect()->back()->with('error', $message);
            }
        }

        return $next($request);
    }
}
