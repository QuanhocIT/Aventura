<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantRateLimit
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly QuotaService $quota,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Không rate limit nếu chưa đăng nhập hoặc không có restaurant
        if (! $user || ! $user->restaurant_id) {
            return $next($request);
        }

        $restaurant = $user->restaurant;

        if (! $restaurant) {
            return $next($request);
        }

        $maxAttempts = $this->quota->getRateLimit($restaurant);
        $key = 'tenant_rate_limit:'.$restaurant->id;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'Vượt quá giới hạn request. Vui lòng thử lại sau.',
                'retry_after' => $retryAfter,
                'plan' => $restaurant->plan?->name,
                'limit' => $maxAttempts.' requests/phút',
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        $this->limiter->hit($key, 60); // decay 60 giây

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - $this->limiter->attempts($key)),
        ]);
    }
}
