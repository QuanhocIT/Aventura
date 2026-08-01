<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdminStepUp
{
    private const SESSION_KEY = 'superadmin.2fa_verified_until';

    private const SESSION_USER_KEY = 'superadmin.2fa_verified_user_id';

    private const SENSITIVE_ROUTE_PATTERNS = [
        'superadmin.accounts.*',
        'superadmin.restaurants.store',
        'superadmin.restaurants.status',
        'superadmin.restaurants.plan',
        'superadmin.restaurants.unflag',
        'superadmin.restaurants.storage-quota',
        'superadmin.restaurants.sandbox',
        'superadmin.tenant-health.*',
        'superadmin.restaurants.billing-overrides.store',
        'superadmin.restaurants.custom-plan.store',
        'superadmin.churn.*',
        'superadmin.banners.*',
        'superadmin.news.*',
        'superadmin.billing.invoices.*',
        'superadmin.billing.webhooks.*',
        'superadmin.billing.dunning.*',
        'superadmin.coupons.*',
        'superadmin.campaign-templates.*',
        'superadmin.plans.store',
        'superadmin.plans.update',
        'superadmin.plans.destroy',
        'superadmin.plans.restore',
        'superadmin.referrals.settings.update',
        'superadmin.referrals.withdrawals.*',
        'superadmin.support.*',
        'superadmin.chatbot.*',
        'superadmin.chatbot-diagnostics.*',
        'superadmin.campaigns.*',
        'superadmin.settings.update',
        'superadmin.settings.test-email',
        'superadmin.service-monitor.*',
        'superadmin.maintenance-schedules.*',
        'superadmin.garbage-collector.cleanup',
        'superadmin.meilisearch-console.*',
        'superadmin.dashboard.report-subscription.update',
        'superadmin.firewall.*',
        'superadmin.security-center.*',
        'superadmin.backup-maintenance.*',
        'superadmin.impersonate.start',
        'superadmin.demo-bookings.*',
    ];

    public static function sessionKey(): string
    {
        return self::SESSION_KEY;
    }

    public static function sessionUserKey(): string
    {
        return self::SESSION_USER_KEY;
    }

    public function handle(Request $request, Closure $next, string $mode = 'write'): Response
    {
        $requiresStepUp = $mode === 'always'
            || (! $request->isMethodSafe() && $request->routeIs(...self::SENSITIVE_ROUTE_PATTERNS));

        if (! $requiresStepUp || $this->isStepUpValid($request)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cần xác nhận mã 2FA trước khi thực hiện thao tác nhạy cảm.',
                'code' => 'superadmin_2fa_required',
                'redirect' => route('superadmin.security.confirm-2fa'),
            ], 423);
        }

        return redirect()
            ->route('superadmin.security.confirm-2fa')
            ->with('error', 'Cần xác nhận mã 2FA trước khi thực hiện thao tác nhạy cảm.');
    }

    private function isStepUpValid(Request $request): bool
    {
        $expiresAt = $request->session()->get(self::SESSION_KEY);
        $verifiedUserId = $request->session()->get(self::SESSION_USER_KEY);

        if (! is_numeric($expiresAt)
            || (int) $verifiedUserId !== (int) $request->user()?->id
            || now()->timestamp >= (int) $expiresAt
        ) {
            $request->session()->forget([self::SESSION_KEY, self::SESSION_USER_KEY]);

            return false;
        }

        return true;
    }
}
