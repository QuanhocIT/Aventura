<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecuritySessionFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        $currentVersion = (int) ($user->security_session_version ?? 0);
        $sessionVersion = $request->session()->get('security_session_version');

        if ($sessionVersion === null) {
            $request->session()->put('security_session_version', $currentVersion);

            return $next($request);
        }

        if ((int) $sessionVersion !== $currentVersion) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->header('X-Inertia')) {
                return Inertia::location('/login');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Phiên đăng nhập đã bị thu hồi. Vui lòng đăng nhập lại.',
                    'security_session_revoked' => true,
                ], 401);
            }

            return redirect('/login')->withErrors([
                'email' => 'Phiên đăng nhập đã bị thu hồi. Vui lòng đăng nhập lại.',
            ]);
        }

        return $next($request);
    }
}
