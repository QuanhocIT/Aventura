<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class CustomRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        return redirect()->intended('/dashboard');
    }
}
