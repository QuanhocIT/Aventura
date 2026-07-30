<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\SubscriptionPlan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    use \App\Concerns\GeneratesSignedCaptcha;

    public function register(): void
    {
        $this->app->bind(\Laravel\Fortify\Http\Requests\TwoFactorLoginRequest::class, \App\Http\Requests\CustomTwoFactorLoginRequest::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();

        $this->app->singleton(\Laravel\Fortify\Contracts\LoginResponse::class, \App\Http\Responses\CustomLoginResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\RegisterResponse::class, \App\Http\Responses\CustomRegisterResponse::class);
        $this->app->singleton(\Laravel\Fortify\Contracts\TwoFactorLoginResponse::class, \App\Http\Responses\CustomTwoFactorLoginResponse::class);

        Fortify::authenticateUsing(function (Request $request) {
            $ip = $request->ip();
            $decay = config('firewall.waf.login.decay_seconds', 10);
            $failedAttempts = \Illuminate\Support\Facades\Cache::get("waf:failed_attempts:{$ip}", []);
            $failedAttemptsCount = count(array_filter(
                $failedAttempts,
                fn($timestamp) => (time() - $timestamp) <= $decay
            ));

            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');

            if ($failedAttemptsCount >= 3) {
                if ($turnstileSiteKey) {
                    $token = $request->input('cf-turnstile-response');
                    if (!$token || !$this->verifyTurnstile($token)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'email' => ['Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.'],
                        ]);
                    }
                } else {
                    $captchaAnswer = $request->input('captcha_answer');
                    $captchaToken = $request->input('captcha_token');
                    if (!$this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'email' => ['Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.'],
                        ]);
                    }
                }
            }

            $users = \App\Models\User::where('email', $request->email)->get();

            $matchedUsers = $users->filter(function ($u) use ($request) {
                return \Illuminate\Support\Facades\Hash::check($request->password, $u->password);
            });

            if ($matchedUsers->isEmpty()) {
                return null;
            }

            $activeUsers = $matchedUsers->filter(fn($u) => $u->status === 'active');

            if ($activeUsers->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['Tài khoản của bạn đã bị khóa hoặc tạm ngưng hoạt động. Vui lòng liên hệ quản lý.'],
                ]);
            }

            // Nếu chỉ có đúng 1 tài khoản hoạt động, áp dụng kiểm tra ca làm việc ngay lập tức
            if ($activeUsers->count() === 1) {
                $user = $activeUsers->first();
                if ($user->restaurant_id && !$user->isExemptFromShiftLock()) {
                    if (!app()->runningUnitTests() || \App\Http\Middleware\SetTenantContext::$enforceShiftLockInTests) {
                        $employee = $user->employee;
                        if (!$employee || !$employee->isWithinScheduledShift()) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'email' => ['Tài khoản của bạn chỉ được phép truy cập trong khung giờ ca làm việc được xếp.'],
                            ]);
                        }
                    }
                }
                return $user;
            }

            // Nếu có nhiều hơn 1 tài khoản hoạt động dưới email này:
            // Lưu thông tin vào session để CustomLoginResponse chuyển hướng qua trang chọn nhà hàng
            $user = $activeUsers->first();
            session(['multi_tenant_users' => $activeUsers->pluck('id', 'restaurant_id')->toArray()]);

            return $user;
        });
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $ip = $request->ip();
            $decay = config('firewall.waf.login.decay_seconds', 10);
            $failedAttempts = \Illuminate\Support\Facades\Cache::get("waf:failed_attempts:{$ip}", []);
            $failedAttemptsCount = count(array_filter(
                $failedAttempts,
                fn($timestamp) => (time() - $timestamp) <= $decay
            ));

            $captchaQuestion = null;
            $captchaToken = null;
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');

            if ($failedAttemptsCount >= 3 && !$turnstileSiteKey) {
                $num1 = rand(1, 10);
                $num2 = rand(1, 10);
                $operator = rand(0, 1) ? '+' : '-';
                if ($operator === '-') {
                    if ($num1 < $num2) {
                        $temp = $num1;
                        $num1 = $num2;
                        $num2 = $temp;
                    }
                    $answer = $num1 - $num2;
                } else {
                    $answer = $num1 + $num2;
                }
                $captchaToken = $this->generateCaptchaToken((string)$answer);
                $captchaQuestion = "{$num1} {$operator} {$num2} = ?";
            }

            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister'      => Features::enabled(Features::registration()),
                'status'           => $request->session()->get('status') ?? $request->query('status'),
                'plans'            => $this->activePlans(),
                'failedAttemptsCount' => $failedAttemptsCount,
                'turnstileSiteKey' => $turnstileSiteKey,
                'captchaQuestion'  => $captchaQuestion,
                'captchaToken'     => $captchaToken,
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email'         => $request->email,
            'token'         => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(function (Request $request) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
            $captchaQuestion = null;
            $captchaToken = null;
            if (!$turnstileSiteKey) {
                $num1 = rand(1, 10);
                $num2 = rand(1, 10);
                $operator = rand(0, 1) ? '+' : '-';
                if ($operator === '-') {
                    if ($num1 < $num2) {
                        $temp = $num1;
                        $num1 = $num2;
                        $num2 = $temp;
                    }
                    $answer = $num1 - $num2;
                } else {
                    $answer = $num1 + $num2;
                }
                $captchaToken = $this->generateCaptchaToken((string)$answer);
                $captchaQuestion = "{$num1} {$operator} {$num2} = ?";
            }
            return Inertia::render('auth/ForgotPassword', [
                'status' => $request->session()->get('status'),
                'turnstileSiteKey' => $turnstileSiteKey,
                'captchaQuestion'  => $captchaQuestion,
                'captchaToken'     => $captchaToken,
            ]);
        });

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function (Request $request) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
            $captchaQuestion = null;
            $captchaToken = null;
            if (!$turnstileSiteKey) {
                $num1 = rand(1, 10);
                $num2 = rand(1, 10);
                $operator = rand(0, 1) ? '+' : '-';
                if ($operator === '-') {
                    if ($num1 < $num2) {
                        $temp = $num1;
                        $num1 = $num2;
                        $num2 = $temp;
                    }
                    $answer = $num1 - $num2;
                } else {
                    $answer = $num1 + $num2;
                }
                $captchaToken = $this->generateCaptchaToken((string)$answer);
                $captchaQuestion = "{$num1} {$operator} {$num2} = ?";
            }
            return Inertia::render('auth/Register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
                'plans'         => $this->activePlans(),
                'turnstileSiteKey' => $turnstileSiteKey,
                'captchaQuestion'  => $captchaQuestion,
                'captchaToken'     => $captchaToken,
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function activePlans(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('active_plans', 3600, function () {
            // is_custom = false: gói riêng do SuperAdmin dựng cho MỘT khách hàng cụ thể
            // (CustomPlanBuilderController) không được rao giá công khai ở trang đăng nhập.
            // HomeController vốn đã lọc điều kiện này — chỗ này trước đây thì không.
            return SubscriptionPlan::where('status', 'active')
                ->where('is_custom', false)
                ->orderBy('price')
                ->get()
                ->map(fn (SubscriptionPlan $p) => [
                    'id'            => $p->id,
                    'code'          => $p->code,
                    'name'          => $p->name,
                    'price'         => (int) $p->price,
                    'billing_cycle' => $p->billing_cycle,
                    'max_branches'  => $p->max_branches,
                    'max_tables'    => $p->max_tables,
                    'max_users'     => $p->max_users,
                    'features'      => $p->features ?? [],
                ])
                ->all();
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            // Giới hạn tối đa 5 lần thử trong 15 phút (900s) cho 2FA
            return Limit::perMinutes(15, 5)->by($request->session()->get('login.id') ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            // Giới hạn tối đa 5 lần thử trong 15 phút (900s) cho login để chống brute-force
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinutes(15, 5)->by($throttleKey);
        });
    }

    /**
     * Verify Cloudflare Turnstile token.
     */
    private function verifyTurnstile(string $token): bool
    {
        $secret = env('TURNSTILE_SECRET_KEY') ?: \App\Models\SystemSetting::get('turnstile_secret_key');
        if (!$secret) {
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            return $response->json('success') === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
