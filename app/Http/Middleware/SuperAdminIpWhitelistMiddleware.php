<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminIpWhitelistMiddleware
{
    /**
     * Chặn truy cập khu vực SuperAdmin từ các địa chỉ IP ngoài danh sách Whitelist.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIpsConfig = config('auth.superadmin_allowed_ips', env('SUPERADMIN_ALLOWED_IPS', ''));

        if (! empty($allowedIpsConfig)) {
            $allowedIps = array_map('trim', explode(',', $allowedIpsConfig));
            $clientIp = $request->ip();

            if (! in_array($clientIp, $allowedIps, true)) {
                Log::warning("Cảnh báo an ninh: IP {$clientIp} cố gắng truy cập khu vực SuperAdmin nhưng không thuộc Whitelist.");

                abort(403, 'Địa chỉ IP của bạn không nằm trong danh sách được phép truy cập trang quản trị SuperAdmin.');
            }
        }

        return $next($request);
    }
}
