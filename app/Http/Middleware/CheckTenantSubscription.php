<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
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

        if ($restaurant->status === 'suspended') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tài khoản doanh nghiệp đã bị khóa. Vui lòng liên hệ bộ phận hỗ trợ.'], 403)
                : abort(403, 'Tài khoản doanh nghiệp đã bị khóa. Vui lòng liên hệ bộ phận hỗ trợ.');
        }

        if ($restaurant->status === 'expired' && ! $request->isMethod('GET') && ! $request->isMethod('HEAD') && ! $request->isMethod('OPTIONS')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Gói dịch vụ đã hết hạn. Vui lòng gia hạn để tiếp tục sử dụng tính năng này.'], 402)
                : abort(402, 'Gói dịch vụ đã hết hạn. Vui lòng gia hạn để tiếp tục sử dụng tính năng này.');
        }

        return $next($request);
    }
}