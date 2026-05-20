<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\Authenticatable;

class CustomRedirectAfterLogin
{
    public function __invoke(Authenticatable $user)
    {
        // Nếu là admin thì về dashboard super admin
        if ($user->hasRole('admin')) {
            return '/super-admin/dashboard';
        }
        // Các user khác về dashboard thường
        return '/dashboard';
    }
}
