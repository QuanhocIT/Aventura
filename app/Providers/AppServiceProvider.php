<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\LoginEvent;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShiftClosing;
use App\Models\SystemSetting;
use App\Models\ViolationReport;
use App\Observers\AuditLogObserver;
use App\Observers\MediaAssetObserver;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\SalaryRecalculationObserver;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\OrderRepositoryInterface;
use App\Services\SecurityAlertService;
use App\Support\MaterializedViews\MaterializedViewRegistry;
use App\Support\Tenant\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

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

        $this->app->singleton(MaterializedViewRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootLocalIpv4SmtpTransport();

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

        // Tự gửi email xác thực ngay sau khi đăng ký (Laravel không tự đăng ký
        // listener này khi project không có EventServiceProvider riêng).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Lưu trữ ID nhân viên và thời gian kết thúc ca trực vào Session khi login thành công
        Event::listen(Login::class, function (Login $event) {
            $user = $event->user;
            session(['security_session_version' => (int) ($user->security_session_version ?? 0)]);

            try {
                if (Schema::hasTable('login_events')) {
                    LoginEvent::create([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'success',
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'occurred_at' => now(),
                    ]);
                }
            } catch (\Throwable) {
                // Login must not fail because an observability table is unavailable.
            }

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

        // WAF Rule: Listen to failed login attempts to block IP
        Event::listen(Failed::class, function (Failed $event) {
            $ip = request()->ip();

            try {
                if (Schema::hasTable('login_events')) {
                    $credentials = $event->credentials ?? [];
                    LoginEvent::create([
                        'user_id' => $event->user?->id,
                        'email' => $event->user?->email ?: ($credentials['email'] ?? $credentials['username'] ?? null),
                        'status' => 'failed',
                        'ip_address' => $ip,
                        'user_agent' => request()->userAgent(),
                        'failure_reason' => 'invalid_credentials',
                        'occurred_at' => now(),
                    ]);
                }
            } catch (\Throwable) {
                // WAF and authentication must remain available if logging is unavailable.
            }

            $key = "waf:failed_attempts:{$ip}";

            $maxAttempts = (int) (SystemSetting::get('waf_login_max_attempts') ?? config('firewall.waf.login.max_attempts', 5));
            $decaySeconds = (int) (SystemSetting::get('waf_login_decay_seconds') ?? config('firewall.waf.login.decay_seconds', 10));
            $blockMinutes = (int) (SystemSetting::get('waf_login_block_minutes') ?? config('firewall.waf.login.block_minutes', 30));

            $now = time();
            $attempts = Cache::get($key, []);

            // Filter out attempts outside the decay time window
            $attempts = array_filter($attempts, function ($timestamp) use ($now, $decaySeconds) {
                return ($now - $timestamp) <= $decaySeconds;
            });

            $attempts[] = $now;

            if (count($attempts) >= $maxAttempts) {
                // Block the IP
                Cache::put("waf:blocked:{$ip}", true, $blockMinutes * 60);

                // Add to blocked list in cache for Admin UI
                $blockedList = Cache::get('waf:blocked_list', []);
                // Filter out expired ones
                $blockedList = array_filter($blockedList, function ($expiry) {
                    return $expiry > time();
                });
                $blockedList[$ip] = time() + ($blockMinutes * 60);
                Cache::put('waf:blocked_list', $blockedList, 86400 * 30);

                // Log security warning
                Log::warning("WAF Blocked IP {$ip} due to {$maxAttempts} failed login attempts within {$decaySeconds}s.");

                // Send Telegram Alert
                SecurityAlertService::sendWafBlockAlert($ip, $maxAttempts, $decaySeconds, $blockMinutes);

                // Clear attempts history
                Cache::forget($key);
            } else {
                Cache::put($key, $attempts, $decaySeconds);
            }
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

        RateLimiter::for('voucher_apply', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Quá nhiều yêu cầu áp dụng mã giảm giá. Vui lòng thử lại sau.',
                    'retry_after' => 60,
                ], 429));
        });

        RateLimiter::for('qr_order_submit', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(fn () => response()->json([
                    'message' => 'Quá nhiều yêu cầu gửi đơn hàng. Vui lòng thử lại sau.',
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

    /**
     * Chỉ ở môi trường local: override transport 'smtp' để ép kết nối bằng IPv4.
     *
     * Máy dev (Windows/Laragon) ở đây phân giải smtp.gmail.com ra cả IPv6 (kèm một
     * bản ghi rác fd00:db80::1) nhưng KHÔNG có tuyến IPv6 → mỗi lần gửi mail treo
     * ~20s chờ IPv6 timeout rồi mới rơi về IPv4, thỉnh thoảng lỗi hẳn. Ta nối thẳng
     * tới IPv4 đã phân giải nhưng vẫn xác thực chứng chỉ TLS theo đúng tên miền
     * (peer_name) để không mất an toàn. Production không bị ảnh hưởng (giữ 'smtp' mặc định).
     */
    private function bootLocalIpv4SmtpTransport(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        Mail::extend('smtp', function (array $config) {
            $host = $config['host'] ?? 'smtp.gmail.com';
            $port = (int) ($config['port'] ?? 587);
            $implicitTls = ($config['scheme'] ?? null) === 'smtps' || $port === 465;

            $transport = new EsmtpTransport(
                $host,
                $port,
                $implicitTls ?: null,
            );

            // Nối tới IPv4 đã phân giải, nhưng vẫn verify cert theo hostname gốc.
            $ipv4 = gethostbynamel($host)[0] ?? null;
            $stream = $transport->getStream();
            if ($ipv4 && $ipv4 !== $host
                && $stream instanceof SocketStream) {
                $stream->setHost($ipv4);
                $stream->setStreamOptions(['ssl' => ['peer_name' => $host]]);
            }

            if (! empty($config['username'])) {
                $transport->setUsername($config['username']);
                $transport->setPassword($config['password'] ?? '');
            }

            return $transport;
        });
    }
}
