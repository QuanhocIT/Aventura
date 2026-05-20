<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class CustomLoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();
        // Định danh cứng tài khoản superadmin
        if ($user && $user->email === 'superadmin@aventura.local') {
            return redirect('/super-admin/dashboard');
        }
        return redirect('/dashboard');
    }
}
