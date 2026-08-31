<?php

namespace App\Actions\Fortify;

use App\Models\User;

class CustomRedirectAfterLogin
{
    public function __invoke(User $user)
    {
        if ($user->isPlatformAdmin()) {
            return '/super-admin/dashboard';
        }

        // Các user khác về dashboard thường
        return '/dashboard';
    }
}
