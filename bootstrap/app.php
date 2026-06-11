<?php

use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\ClearPermissionCache;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireSuperAdminTwoFactor;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\TenantQuotaMiddleware;
use App\Http\Middleware\TenantRateLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            SetTenantContext::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.active' => CheckTenantSubscription::class,
            'tenant.subscription' => CheckTenantSubscription::class,
            'tenant.ratelimit' => TenantRateLimit::class,
            'tenant.quota' => TenantQuotaMiddleware::class,
            'role' => RoleMiddleware::class,
            'role.superadmin.2fa' => RequireSuperAdminTwoFactor::class,
            'permission' => PermissionMiddleware::class,
            'permission.cache.clear' => ClearPermissionCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
