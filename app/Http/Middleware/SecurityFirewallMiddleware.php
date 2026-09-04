<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SecurityFirewallMiddleware
{
    /**
     * The URIs that should be excluded from rate limiting.
     *
     * @var array<int, string>
     */
    protected array $exceptRateLimit = [
        'up',
        'api/health',
        'webhooks/payments',
        'webhooks/sepay/bank',
        'api/webhooks/payments/*',
        'api/webhooks/delivery/*',
        'api/pos/*',
        'api/online/*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        // 1. Whitelist Check: If IP is whitelisted, bypass all WAF and Rate Limiting
        $whitelist = json_decode(SystemSetting::get('firewall_whitelist', '[]'), true);
        if (in_array($ip, $whitelist, true)) {
            return $next($request);
        }

        // 2. WAF Check: Check if the IP is blocked
        if (Cache::has("waf:blocked:{$ip}")) {
            $message = 'IP của bạn đã bị chặn tạm thời do phát hiện hành vi spam hoặc tấn công. Vui lòng thử lại sau 30 phút.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            return response($message, 403);
        }

        // 3. Skip Rate Limiting for excluded routes
        if ($this->shouldSkipRateLimit($request)) {
            return $next($request);
        }

        // 4. Determine Rate Limiting limits
        $maxAttempts = (int) (SystemSetting::get('rate_limit_global_max') ?? config('firewall.rate_limit.global.max_attempts', 60));
        $decaySeconds = (int) (SystemSetting::get('rate_limit_global_decay') ?? config('firewall.rate_limit.global.decay_seconds', 60));

        // Route-specific rate limits: keep sensitive auth endpoints protected,
        // while allowing normal login retries without replacing the page with
        // a raw 429 response.
        $rateLimitKeyPrefix = 'rate_limit';
        $isLoginRoute = $request->is('login') && $request->isMethod('POST');
        $isAuthRoute = ($request->is('register') || $request->is('forgot-password') || $request->is('reset-password') || $request->is('two-factor-challenge') || $request->is('lock-screen') || $request->is('api/v1/auth/*')) && $request->isMethod('POST');

        if ($isLoginRoute) {
            $maxAttempts = (int) config('firewall.rate_limit.auth.max_attempts', 20);
            $decaySeconds = (int) config('firewall.rate_limit.auth.decay_seconds', 900);
            $rateLimitKeyPrefix = 'rate_limit:auth:login';
        } elseif ($isAuthRoute) {
            $maxAttempts = 5;
            $decaySeconds = 900; // 15 phút
            $rateLimitKeyPrefix = 'rate_limit:auth:'.str_replace('/', '_', $request->path());
        }

        $user = $request->user();
        $token = $request->bearerToken();

        if ($request->is('register') || $request->is('forgot-password')) {
            $key = $rateLimitKeyPrefix.':ip:'.$ip;
        } elseif ($user) {
            $key = $rateLimitKeyPrefix.':user:'.$user->id;
        } elseif ($token) {
            $key = $rateLimitKeyPrefix.':token:'.sha1($token);
        } else {
            $key = $rateLimitKeyPrefix.':ip:'.$ip;
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            $message = 'Vượt quá giới hạn request. Vui lòng thử lại sau.';

            if (
                $request->header('X-Inertia')
                || ($isLoginRoute && ! $request->expectsJson())
            ) {
                return redirect()->back(303)
                    ->withErrors(['email' => $message])
                    ->with('rate_limit_retry_after', $retryAfter)
                    ->withHeaders([
                        'X-RateLimit-Limit' => $maxAttempts,
                        'X-RateLimit-Remaining' => 0,
                        'Retry-After' => $retryAfter,
                    ]);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $message,
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'X-RateLimit-Limit' => $maxAttempts,
                    'X-RateLimit-Remaining' => 0,
                    'Retry-After' => $retryAfter,
                ]);
            }

            return response($message, 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);

        $response = $next($request);

        $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));

        if (method_exists($response, 'withHeaders')) {
            return $response->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => $remaining,
            ]);
        }

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        return $response;
    }

    /**
     * Determine if the request has a URI that should bypass rate limiting.
     */
    protected function shouldSkipRateLimit(Request $request): bool
    {
        // Bỏ qua rate limit toàn cục cho các request đồng bộ ngầm (Inertia partial reload)
        // để Bếp, Thu ngân và Khách không bị văng/chặn khi chạy polling liên tục trong ca trực
        if ($request->isMethod('GET') && $request->hasHeader('X-Inertia-Partial-Data')) {
            return true;
        }

        // Bỏ qua menu xem món của khách (được kiểm soát riêng ở cấp độ route)
        if ($request->isMethod('GET') && $request->is('customer/order/*')) {
            return true;
        }

        foreach ($this->exceptRateLimit as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->fullUrlIs($except) || $request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
