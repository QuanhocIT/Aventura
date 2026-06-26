<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Legacy super_admin role bypasses all permission checks
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if (! $user->hasPermissionTo($permission)) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}
