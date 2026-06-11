<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantQuotaMiddleware
{
    public function __construct(
        private readonly QuotaService $quota,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $user = $request->user();

        if (! $user || ! $user->restaurant_id) {
            return $next($request);
        }

        $restaurant = $user->restaurant;

        if (! $restaurant) {
            return $next($request);
        }

        // Chặn request POST (tạo mới) nếu đã đạt giới hạn hạn ngạch
        if ($request->isMethod('POST') && ! $this->quota->canAdd($restaurant, $resource)) {
            $planName = $restaurant->plan?->name ?? 'Free';
            $limit = $this->quota->getLimit($restaurant, $resource);

            $resourceNames = [
                'branches' => 'chi nhánh',
                'tables' => 'bàn hoạt động',
                'employees' => 'tài khoản nhân viên',
                'areas' => 'khu vực',
            ];

            $resourceName = $resourceNames[$resource] ?? $resource;

            $message = "Gói dịch vụ hiện tại ({$planName}) của bạn đã đạt giới hạn tối đa ({$limit} {$resourceName}). Vui lòng nâng cấp lên gói PRO để mở khóa không giới hạn!";

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : abort(403, $message);
        }

        return $next($request);
    }
}
