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

        if ($request->isMethod('GET') && ! $request->is('api/*') && ! $request->expectsJson()) {
            return $next($request);
        }

        $maxAttempts = $this->quota->getRateLimit($restaurant);
        $key = 'tenant_rate_limit:'.$restaurant->id;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            $message = 'Vượt quá giới hạn request. Vui lòng thử lại sau.';
            $headers = [
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ];

            if ($request->header('X-Inertia') || (! $request->expectsJson() && ! $request->is('api/*'))) {
                return redirect()->back(303)
                    ->withErrors(['request' => $message])
                    ->with('rate_limit_retry_after', $retryAfter)
                    ->withHeaders($headers);
            }

            return response()->json([
                'message' => $message,
                'retry_after' => $retryAfter,
                'plan' => $restaurant->plan?->name,
                'limit' => $maxAttempts.' requests/phút',
            ], 429)->withHeaders($headers);
        }

        $this->limiter->hit($key, 60); // decay 60 giây

        $response = $next($request);

        if (method_exists($response, 'withHeaders')) {
            return $response->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => max(0, $maxAttempts - $this->limiter->attempts($key)),
            ]);
        }

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $this->limiter->attempts($key)));

        return $response;
    }
}
