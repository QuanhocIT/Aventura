<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

        // Tự gửi email xác thực ngay sau khi đăng ký (Laravel không tự đăng ký
        // listener này khi project không có EventServiceProvider riêng).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Bảo mật trang Pulse: Chỉ cho phép Chủ nhà hàng (owner) hoặc Quản lý (manager) xem
        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user = null) {
            return optional($user)->hasAnyRole(['owner', 'manager']);
        });

        $this->configureDefaults();
        $this->loadDynamicSettings();
    }

    /**
     * Load dynamic configurations from the database and override the default Laravel config.
     */
    protected function loadDynamicSettings(): void
    {
        try {
            $mailDriver = \App\Models\SystemSetting::get('mail_driver');
            if ($mailDriver) {
                config(['mail.default' => $mailDriver]);

                if ($mailDriver === 'smtp') {
                    config([
                        'mail.mailers.smtp.host' => \App\Models\SystemSetting::get('mail_smtp_host', config('mail.mailers.smtp.host')),
                        'mail.mailers.smtp.port' => (int) \App\Models\SystemSetting::get('mail_smtp_port', config('mail.mailers.smtp.port', 587)),
                        'mail.mailers.smtp.username' => \App\Models\SystemSetting::get('mail_smtp_username', config('mail.mailers.smtp.username')),
                        'mail.mailers.smtp.password' => \App\Models\SystemSetting::get('mail_smtp_password', config('mail.mailers.smtp.password')),
                        'mail.mailers.smtp.encryption' => \App\Models\SystemSetting::get('mail_smtp_encryption', config('mail.mailers.smtp.encryption', 'tls')),
                    ]);
                } elseif ($mailDriver === 'ses') {
                    config([
                        'services.ses.key' => \App\Models\SystemSetting::get('mail_ses_key', config('services.ses.key')),
                        'services.ses.secret' => \App\Models\SystemSetting::get('mail_ses_secret', config('services.ses.secret')),
                        'services.ses.region' => \App\Models\SystemSetting::get('mail_ses_region', config('services.ses.region', 'us-east-1')),
                    ]);
                } elseif ($mailDriver === 'mailgun') {
                    config([
                        'services.mailgun.domain' => \App\Models\SystemSetting::get('mail_mailgun_domain', config('services.mailgun.domain')),
                        'services.mailgun.secret' => \App\Models\SystemSetting::get('mail_mailgun_secret', config('services.mailgun.secret')),
                        'services.mailgun.endpoint' => \App\Models\SystemSetting::get('mail_mailgun_endpoint', config('services.mailgun.endpoint', 'api.mailgun.net')),
                    ]);
                }

                $fromAddress = \App\Models\SystemSetting::get('mail_from_address');
                if ($fromAddress) {
                    config(['mail.from.address' => $fromAddress]);
                }

                $fromName = \App\Models\SystemSetting::get('mail_from_name');
                if ($fromName) {
                    config(['mail.from.name' => $fromName]);
                }
            }
        } catch (\Throwable $e) {
            // DB connection or table not ready during migrations/setup, ignore
        }
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
