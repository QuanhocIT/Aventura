<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShiftClosing;
use App\Models\SystemSetting;
use App\Models\ViolationReport;
use App\Observers\AuditLogObserver;
use App\Observers\MediaAssetObserver;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;
use App\Observers\SalaryRecalculationObserver;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\OrderRepositoryInterface;
use App\Support\Tenant\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! app()->runningInConsole()) {
            if (
                str_contains(request()->getHost(), 'ngrok') ||
                request()->header('X-Forwarded-Proto') === 'https' ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ) {
                URL::forceScheme('https');
            }
        }

        $this->loadMigrationsFrom([
            database_path('migrations/tenant'),
            database_path('migrations/restaurant'),
            database_path('migrations/hr'),
            database_path('migrations/analytics'),
            database_path('migrations/system'),
        ]);

        Order::observe(OrderObserver::class);
        AuditLog::observe(AuditLogObserver::class);
        ViolationReport::observe(SalaryRecalculationObserver::class);
        ShiftClosing::observe(SalaryRecalculationObserver::class);
        InventoryTransaction::observe(SalaryRecalculationObserver::class);
        MediaAsset::observe(MediaAssetObserver::class);
        Payment::observe(PaymentObserver::class);
        Product::observe(ProductObserver::class);

        // Tự gửi email xác thực ngay sau khi đăng ký (Laravel không tự đăng ký
        // listener này khi project không có EventServiceProvider riêng).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Lưu trữ ID nhân viên và thời gian kết thúc ca trực vào Session khi login thành công
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;
            $employee = $user->employee;
            if ($employee) {
                session([
                    'employee_id' => $employee->id,
                    'shift_allowed_until' => $employee->getShiftAllowedUntil(),
                ]);
            } else {
                session()->forget(['employee_id', 'shift_allowed_until']);
            }
        });

        // Bảo mật trang Pulse: Chỉ cho phép Chủ nhà hàng (owner) hoặc Quản lý (manager) xem
        Gate::define('viewPulse', function ($user = null) {
            return optional($user)->hasAnyRole(['owner', 'manager']);
        });

        $this->configureDefaults();
        $this->loadDynamicSettings();
        $this->configureRateLimiters();
        $this->configureMicroserviceAuth();

        // Fortify's own route file (vendor/laravel/fortify/routes/routes.php) only wires
        // its 'limiters' config into the login/two-factor routes — password reset and
        // registration are registered with no throttle at all. Attach it here instead of
        // patching the vendor file. Deferred to app()->booted() so it runs after Fortify's
        // provider has registered those routes, regardless of provider boot order.
        $this->app->booted(function () {
            $targets = ['password.email' => 'throttle:5,1', 'password.update' => 'throttle:5,1', 'register.store' => 'throttle:5,1'];

            // getByName()'s lookup table is built at the moment each route is added to the
            // collection, which happens before the chained ->name() call runs — so it can miss
            // routes registered this way. Iterating and matching on the route's current name
            // (as read live from the route object) sidesteps that staleness.
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if (isset($targets[$route->getName()])) {
                    $route->middleware($targets[$route->getName()]);
                }
            }
        });
    }

    /**
     * The Python microservices (email/chatbot/analytics) have no authentication of
     * their own, so every outgoing request whose host:port matches one of them gets
     * a shared-secret header attached here — one place, covers every call site.
     */
    protected function configureMicroserviceAuth(): void
    {
        $internalKey = config('services.microservice_internal_key');

        if (empty($internalKey)) {
            return;
        }

        $microserviceHostPorts = collect([
            config('services.email_microservice.url'),
            config('services.chatbot.url'),
            config('services.analytics.url'),
        ])
            ->filter()
            ->map(function (string $url) {
                $parts = parse_url($url);

                return ($parts['host'] ?? '').':'.($parts['port'] ?? '');
            })
            ->all();

        Http::globalRequestMiddleware(function ($request) use ($internalKey, $microserviceHostPorts) {
            $uri = $request->getUri();
            $hostPort = $uri->getHost().':'.$uri->getPort();

            if (in_array($hostPort, $microserviceHostPorts, true)) {
                return $request->withHeader('X-Internal-Key', $internalKey);
            }

            return $request;
        });
    }

    /**
     * Load dynamic configurations from the database and override the default Laravel config.
     */
    protected function loadDynamicSettings(): void
    {
        try {
            $mailDriver = SystemSetting::get('mail_driver');
            if ($mailDriver) {
                config(['mail.default' => $mailDriver]);

                if ($mailDriver === 'smtp') {
                    config([
                        'mail.mailers.smtp.host' => SystemSetting::get('mail_smtp_host', config('mail.mailers.smtp.host')),
                        'mail.mailers.smtp.port' => (int) SystemSetting::get('mail_smtp_port', config('mail.mailers.smtp.port', 587)),
                        'mail.mailers.smtp.username' => SystemSetting::get('mail_smtp_username', config('mail.mailers.smtp.username')),
                        'mail.mailers.smtp.password' => SystemSetting::get('mail_smtp_password', config('mail.mailers.smtp.password')),
                        'mail.mailers.smtp.encryption' => SystemSetting::get('mail_smtp_encryption', config('mail.mailers.smtp.encryption', 'tls')),
                    ]);
                } elseif ($mailDriver === 'ses') {
                    config([
                        'services.ses.key' => SystemSetting::get('mail_ses_key', config('services.ses.key')),
                        'services.ses.secret' => SystemSetting::get('mail_ses_secret', config('services.ses.secret')),
                        'services.ses.region' => SystemSetting::get('mail_ses_region', config('services.ses.region', 'us-east-1')),
                    ]);
                } elseif ($mailDriver === 'mailgun') {
                    config([
                        'services.mailgun.domain' => SystemSetting::get('mail_mailgun_domain', config('services.mailgun.domain')),
                        'services.mailgun.secret' => SystemSetting::get('mail_mailgun_secret', config('services.mailgun.secret')),
                        'services.mailgun.endpoint' => SystemSetting::get('mail_mailgun_endpoint', config('services.mailgun.endpoint', 'api.mailgun.net')),
                    ]);
                }

                $fromAddress = SystemSetting::get('mail_from_address');
                if ($fromAddress) {
                    config(['mail.from.address' => $fromAddress]);
                }

                $fromName = SystemSetting::get('mail_from_name');
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
                ->response(fn () => response()->json([
                    'message' => 'Quá nhiều yêu cầu trong thời gian ngắn. Vui lòng chờ một lúc rồi thử lại.',
                    'retry_after' => 60,
                ], 429));
        });

        // Shipper GPS ping: keyed per shipper device so one runaway/abusive device can't
        // exhaust the whole restaurant's shared tenant.ratelimit quota for everyone else.
        RateLimiter::for('shipper_location', function (Request $request) {
            return Limit::perMinute(120)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Quá nhiều cập nhật vị trí trong thời gian ngắn.',
                ], 429));
        });

        // Batch ping already carries up to 50 points per request, so a lower per-minute cap.
        RateLimiter::for('shipper_location_batch', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Quá nhiều cập nhật vị trí hàng loạt trong thời gian ngắn.',
                ], 429));
        });

        // Payment webhooks: gateways retry on failure, so this is generous, but still
        // bounded to blunt flooding/abuse against unauthenticated public endpoints.
        RateLimiter::for('payment_webhooks', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
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
