<?php

namespace App\Http\Middleware;

use App\Support\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        // Supplier portal users are external collaborators and do not belong
        // to an operational branch of the restaurant.
        if ($user?->hasRole('supplier') || $user?->canViewAllBranches()) {
            return $next($request);
        }

        if (
            $user
            && $user->restaurant_id
            && ! $user->isSuperAdmin()
            && $context->isUnassigned()
        ) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Tài khoản chưa được gán chi nhánh. Vui lòng liên hệ chủ nhà hàng.',
                ], 403);
            }

            abort(403, 'Tài khoản chưa được gán chi nhánh. Vui lòng liên hệ chủ nhà hàng.');
        }

        return $next($request);
    }
}
