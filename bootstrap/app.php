<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CheckServiceMaintenance;
use App\Http\Middleware\CheckShiftSchedule;
use App\Http\Middleware\CheckSuperAdminPermission;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\ClearPermissionCache;
use App\Http\Middleware\CompressResponse;
use App\Http\Middleware\EnforceImpersonateReadOnly;
use App\Http\Middleware\EnsureBranchContext;
use App\Http\Middleware\EnsureSecuritySessionFresh;
use App\Http\Middleware\EnsureSuperAdminAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventSelfApproval;
use App\Http\Middleware\RequireSuperAdminStepUp;
use App\Http\Middleware\RequireSuperAdminTwoFactor;
use App\Http\Middleware\SecurityFirewallMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\SuperAdminIpWhitelistMiddleware;
use App\Http\Middleware\TenantQuotaMiddleware;
use App\Http\Middleware\TenantRateLimit;
use App\Http\Middleware\ValidatePayloadSize;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/super-admin.php'));
        },
    )
    // Nguồn DUY NHẤT khai báo lịch chạy định kỳ (Task Scheduler). Trước đây
    // app/Console/Kernel.php cũng định nghĩa lịch nhưng là DEAD CODE — Laravel 11+
    // withKernels() bind thẳng Illuminate\Foundation\Console\Kernel, không bao giờ
    // gọi tới App\Console\Kernel::schedule(). Đã xoá file đó, gộp về đây.
    ->withSchedule(function (Schedule $schedule): void {
        (require __DIR__.'/../routes/schedule.php')($schedule);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\CorrelationIdMiddleware::class);
        $middleware->prepend(SecurityFirewallMiddleware::class);
        $middleware->prepend(ValidatePayloadSize::class);

        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
            'api/webhooks/payments/vietqr',
            'api/webhooks/payments/vnpay',
            'api/webhooks/payments/momo',
            'api/webhooks/payments/zalopay',
            'api/webhooks/delivery/*',
            'api/pos/*',
            'api/online/*',
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureSecuritySessionFresh::class,
            CheckServiceMaintenance::class,
            CompressResponse::class,
            EnforceImpersonateReadOnly::class,
            HandleAppearance::class,
            SetTenantContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.active' => CheckTenantSubscription::class,
            'tenant.subscription' => CheckTenantSubscription::class,
            'tenant.ratelimit' => TenantRateLimit::class,
            'tenant.branch' => EnsureBranchContext::class,
            'tenant.quota' => TenantQuotaMiddleware::class,
            'auth.apikey' => AuthenticateApiKey::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'role.superadmin.2fa' => RequireSuperAdminTwoFactor::class,
            'permission' => PermissionMiddleware::class,
            'permission.cache.clear' => ClearPermissionCache::class,
            'superadmin.access' => EnsureSuperAdminAccess::class,
            'superadmin.permission' => CheckSuperAdminPermission::class,
            'superadmin.stepup' => RequireSuperAdminStepUp::class,
            'superadmin.ip_whitelist' => SuperAdminIpWhitelistMiddleware::class,
            'shift.schedule' => CheckShiftSchedule::class,
            'prevent_self_approval' => PreventSelfApproval::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
