<?php

namespace App\Http\Responses;

use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class CustomTwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        session()->flash('success', 'Đăng nhập thành công!');

        return CustomLoginResponse::redirectForUser($user);
    }
}
