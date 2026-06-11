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
    public function register(): void
    {
        //
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
                if ($user->restaurant_id && !$user->isSuperAdmin() && !$user->hasAnyRole(['owner', 'manager', 'supplier_admin'])) {
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
            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister'      => Features::enabled(Features::registration()),
                'status'           => $request->session()->get('status'),
                'plans'            => $this->activePlans(),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email'         => $request->email,
            'token'         => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'plans'         => $this->activePlans(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function activePlans(): array
    {
        return SubscriptionPlan::where('status', 'active')
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
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
