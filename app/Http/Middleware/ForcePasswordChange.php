<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('user-password.update', 'security.edit', 'logout')
            || $request->is('logout')
            || str_contains($request->path(), 'two-factor')
            || ($request->routeIs('profile.edit') && $request->query('tab') === 'security')) {
            return $next($request);
        }

        return redirect()->route('profile.edit', ['tab' => 'security'])
            ->with('warning', 'Bạn phải đổi mật khẩu trước khi tiếp tục sử dụng tài khoản.');
    }
}
