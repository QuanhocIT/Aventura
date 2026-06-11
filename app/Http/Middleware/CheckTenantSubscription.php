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
                ? response()->json(['message' => 'Tai khoan doanh nghiep da bi khoa. Vui long lien he bo phan ho tro.'], 403)
                : abort(403, 'Tai khoan doanh nghiep da bi khoa. Vui long lien he bo phan ho tro.');
        }

        if ($restaurant->status === 'expired' && ! $request->isMethod('GET') && ! $request->isMethod('HEAD') && ! $request->isMethod('OPTIONS')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Goi dich vu da het han. Vui long gia han de tiep tuc su dung tinh nang nay.'], 402)
                : abort(402, 'Goi dich vu da het han. Vui long gia han de tiep tuc su dung tinh nang nay.');
        }

        return $next($request);
    }
}
