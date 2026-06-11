<?php

use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/super-admin.php'));
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/supplier-portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\CompressResponse::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            SetTenantContext::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.active'        => CheckTenantSubscription::class,
            'tenant.subscription'  => CheckTenantSubscription::class,
            'tenant.ratelimit'     => \App\Http\Middleware\TenantRateLimit::class,
            'tenant.quota'         => \App\Http\Middleware\TenantQuotaMiddleware::class,
            'role'                 => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role.superadmin.2fa'  => \App\Http\Middleware\RequireSuperAdminTwoFactor::class,
            'permission'           => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'permission.cache.clear' => \App\Http\Middleware\ClearPermissionCache::class,
            'supplier.portal'        => \App\Http\Middleware\EnsureSupplierPortalAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
