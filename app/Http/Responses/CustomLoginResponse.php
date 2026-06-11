<?php

namespace App\Http\Responses;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Spatie\Permission\PermissionRegistrar;

class CustomLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        if (session()->has('multi_tenant_users') && count(session('multi_tenant_users')) > 1) {
            return redirect()->route('choose-restaurant');
        }

        // Nếu user chọn một gói khác gói hiện tại từ trang login → chuyển tới billing
        $desiredCode = Str::lower((string) $request->input('plan_code', ''));
        if ($desiredCode && $desiredCode !== 'free') {
            $currentCode = Str::lower((string) ($user->restaurant?->plan?->code ?? 'free'));
            if ($desiredCode !== $currentCode) {
                $plan = SubscriptionPlan::query()
                    ->whereRaw('LOWER(code) = ?', [$desiredCode])
                    ->where('status', 'active')
                    ->first();

                if ($plan && (float) $plan->price > 0) {
                    return redirect()->route('billing.checkout', ['plan' => $desiredCode]);
                }
            }
        }

        return self::redirectForUser($user);
    }

    public static function redirectForUser(User $user): RedirectResponse
    {
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            //
        }

        if ($user->isSuperAdmin()) {
            return redirect('/super-admin/dashboard');
        }

        if ($user->roles()->exists()) {
            return redirect()->intended('/dashboard');
        }

        return redirect('/');
    }
}
