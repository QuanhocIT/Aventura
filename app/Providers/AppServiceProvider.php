<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Support\Tenant\TenantContext::class, function () {
            return new \App\Support\Tenant\TenantContext();
        });

        $this->app->bind(
            \App\Repositories\OrderRepositoryInterface::class,
            \App\Repositories\Eloquent\EloquentOrderRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/tenant'),
            database_path('migrations/restaurant'),
            database_path('migrations/hr'),
            database_path('migrations/analytics'),
            database_path('migrations/system'),
        ]);

        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\AuditLog::observe(\App\Observers\AuditLogObserver::class);
        \App\Models\ViolationReport::observe(\App\Observers\SalaryRecalculationObserver::class);
        \App\Models\ShiftClosing::observe(\App\Observers\SalaryRecalculationObserver::class);
        \App\Models\InventoryTransaction::observe(\App\Observers\SalaryRecalculationObserver::class);

        // Bảo mật trang Pulse: Chỉ cho phép Chủ nhà hàng (owner) hoặc Quản lý (manager) xem
        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user = null) {
            return optional($user)->hasAnyRole(['owner', 'manager']);
        });

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
