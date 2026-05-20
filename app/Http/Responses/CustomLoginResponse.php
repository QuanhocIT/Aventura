<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class CustomLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return self::redirectForUser($user);
    }

    public static function redirectForUser(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return redirect()->intended('/super-admin/dashboard');
        }

        if ($user->roles()->exists()) {
            return redirect()->intended('/dashboard');
        }

        // Khách hàng / user chưa được gán role → về trang chủ
        return redirect('/');
    }
}
