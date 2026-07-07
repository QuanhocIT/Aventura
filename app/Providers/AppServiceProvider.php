<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        if (!app()->runningInConsole()) {
            if (
                str_contains(request()->getHost(), 'ngrok') ||
                request()->header('X-Forwarded-Proto') === 'https' ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }

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
        \App\Models\MediaAsset::observe(\App\Observers\MediaAssetObserver::class);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        // Tự gửi email xác thực ngay sau khi đăng ký (Laravel không tự đăng ký
        // listener này khi project không có EventServiceProvider riêng).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Lưu trữ ID nhân viên và thời gian kết thúc ca trực vào Session khi login thành công
        Event::listen(\Illuminate\Auth\Events\Login::class, function (\Illuminate\Auth\Events\Login $event) {
            $user = $event->user;
            $employee = $user->employee;
            if ($employee) {
                session([
                    'employee_id' => $employee->id,
                    'shift_allowed_until' => $employee->getShiftAllowedUntil()
                ]);
            } else {
                session()->forget(['employee_id', 'shift_allowed_until']);
            }
        });

        // Bảo mật trang Pulse: Chỉ cho phép Chủ nhà hàng (owner) hoặc Quản lý (manager) xem
        \Illuminate\Support\Facades\Gate::define('viewPulse', function ($user = null) {
            return optional($user)->hasAnyRole(['owner', 'manager']);
        });

        $this->configureDefaults();
        $this->loadDynamicSettings();
        $this->configureRateLimiters();
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
     * Register custom rate limiters for specific route groups.
     */
    protected function configureRateLimiters(): void
    {
        // Employee portal: 60 requests/minute keyed per user to isolate staff traffic
        // from manager/owner tenant-wide limits.
        RateLimiter::for('employee_portal', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn() => response()->json([
                    'message'     => 'Quá nhiều yêu cầu trong thời gian ngắn. Vui lòng chờ một lúc rồi thử lại.',
                    'retry_after' => 60,
                ], 429));
        });

        RateLimiter::for('voucher_apply', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn() => response()->json([
                    'message'     => 'Quá nhiều yêu cầu áp dụng mã giảm giá. Vui lòng thử lại sau.',
                    'retry_after' => 60,
                ], 429));
        });

        RateLimiter::for('qr_order_submit', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(fn() => response()->json([
                    'message'     => 'Quá nhiều yêu cầu gửi đơn hàng. Vui lòng thử lại sau.',
                    'retry_after' => 60,
                ], 429));
        });
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
