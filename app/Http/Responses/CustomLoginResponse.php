<?php

namespace App\Http\Responses;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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

        if (self::isDisabledSupplier($user)) {
            return self::rejectDisabledSupplier($user);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        session()->flash('success', 'Đăng nhập thành công!');

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
                    $cycle = $request->input('cycle', 'monthly');
                    if (! in_array($cycle, ['monthly', 'yearly'])) {
                        $cycle = 'monthly';
                    }

                    return redirect()->route('billing.checkout', ['plan' => $desiredCode, 'cycle' => $cycle]);
                }
            }
        }

        return self::redirectForUser($user);
    }

    public static function redirectForUser(User $user): RedirectResponse
    {
        if (self::isDisabledSupplier($user)) {
            return self::rejectDisabledSupplier($user);
        }

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

    private static function isDisabledSupplier(User $user): bool
    {
        return ! (bool) config('portal.supplier_portal_enabled', false)
            && $user->hasRole('supplier');
    }

    private static function rejectDisabledSupplier(User $user): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/login')->withErrors([
            'email' => 'Cổng Nhà cung cấp hiện đã được tắt. Vui lòng liên hệ quản lý nhà hàng.',
        ]);
    }
}
