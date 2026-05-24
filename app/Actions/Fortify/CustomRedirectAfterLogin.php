<?php

namespace App\Actions\Fortify;

use App\Models\User;

class CustomRedirectAfterLogin
{
    public function __invoke(User $user)
    {
        if ($user->isSuperAdmin()) {
            return '/super-admin/dashboard';
        }
        // CÃ¡c user khÃ¡c vá» dashboard thÆ°á»ng
        return '/dashboard';
    }
}
