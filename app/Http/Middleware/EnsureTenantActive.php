<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->restaurant_id) {
            return $next($request);
        }

        $restaurant = $user->restaurant;

        if (! $restaurant) {
            return $next($request);
        }

        if ($restaurant->status === 'suspended' || $restaurant->lifecycleStatus() === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tài khoản doanh nghiệp đã bị tạm ngưng. Vui lòng liên hệ quản trị viên.',
                    'code'    => 'TENANT_SUSPENDED',
                ], 403);
            }

            abort(403, 'Tài khoản doanh nghiệp của bạn đã bị tạm ngưng. Vui lòng liên hệ support@aventura.vn.');
        }

        if ($restaurant->lifecycleStatus() === 'archived') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tenant đã được lưu trữ.',
                    'code' => 'TENANT_ARCHIVED',
                ], 403);
            }

            abort(403, 'Tenant đã được lưu trữ.');
        }

        if ($restaurant->status === 'expired') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Gói dịch vụ đã hết hạn. Vui lòng gia hạn để tiếp tục.',
                    'code'    => 'TENANT_EXPIRED',
                ], 402);
            }

            abort(402, 'Gói dịch vụ của bạn đã hết hạn. Vui lòng gia hạn tại trang cài đặt.');
        }

        return $next($request);
    }
}
