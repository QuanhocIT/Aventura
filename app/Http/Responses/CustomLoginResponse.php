<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class CustomLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();
// ...existing code...
        // Định danh cứng tài khoản superadmin
        if ($user && $user->email === 'superadmin@aventura.local') {
            return redirect('/super-admin/dashboard');
        }
        return redirect('/dashboard');
// ...existing code...

        if (! $user) {
            return redirect('/login');
        }

        // Cập nhật thời gian đăng nhập cuối
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->hasRole('admin')) {
            return redirect()->intended('/super-admin/dashboard');
        }

        return redirect()->intended('/dashboard');
        if (! $user) {
            return redirect('/login');
        }

        // Cập nhật thời gian đăng nhập cuối
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->hasRole('admin')) {
            return redirect()->intended('/super-admin/dashboard');
        }

        return redirect()->intended('/dashboard');
// ...existing code...
    }
}
