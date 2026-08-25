<?php

namespace App\Concerns;

use Illuminate\Http\Request;

trait AuthorizesRestaurantSettings
{
    protected function authorizeRestaurantSettings(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isSuperAdmin() || $user->can('manage_restaurant_settings')),
            403,
            'Bạn không có quyền quản lý cài đặt nhà hàng.'
        );
    }
}
