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
        app(TenantContext::class)->setRestaurantId($request->user()?->restaurant_id);

        return $next($request);
    }
}
