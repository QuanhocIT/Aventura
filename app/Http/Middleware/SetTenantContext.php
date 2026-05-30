<?php

namespace App\Http\Middleware;

use App\Support\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->status !== 'active') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ quản lý.']);
        }

        if ($user && $user->status === 'active') {
            if (!$request->is('logout') && !$request->routeIs('logout')) {
                if (!$user->isSuperAdmin() && !$user->hasAnyRole(['owner', 'manager'])) {
                    $employee = $user->employee;
                    if (!$employee || !$employee->isWithinScheduledShift()) {
                        auth()->logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                        return redirect('/login')->withErrors(['email' => 'Tài khoản của bạn chỉ được phép truy cập trong khung giờ ca làm việc được xếp.']);
                    }
                }
            }
        }

        app(TenantContext::class)->setRestaurantId($user?->restaurant_id);

        return $next($request);
    }
}
