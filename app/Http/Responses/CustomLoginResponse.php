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
        // Clear Spatie Permission cache to ensure we have the latest roles during login
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore if Spatie Permission registrar is not registered or throws an error
        }

        if ($user->isSuperAdmin()) {
            return redirect('/super-admin/dashboard');
        }

        if ($user->roles()->exists()) {
            return redirect()->intended('/dashboard');
        }

        // KhÃ¡ch hÃ ng / user chÆ°a Ä‘Æ°á»£c gÃ¡n role â†’ vá» trang chá»§
        return redirect('/');
    }
}
