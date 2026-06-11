<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole('supplier_admin')) {
            abort(403, 'Bạn không có quyền truy cập cổng nhà cung cấp.');
        }

        if (! $user->supplier_id) {
            abort(403, 'Tài khoản chưa được liên kết với nhà cung cấp nào.');
        }

        return $next($request);
    }
}
