<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                $user->google_id = $googleUser->getId();
                $user->last_login_at = now();
                if (! $user->email_verified_at) {
                    $user->email_verified_at = now();
                }
                $user->save();
            } else {
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'last_login_at'     => now(),
                ]);
            }

            Auth::login($user, true);

            if ($user->hasRole('admin')) {
                return redirect()->intended('/super-admin/dashboard');
            }

            return redirect()->intended('/dashboard');
        } catch (Exception $e) {
            return redirect('/login')->withErrors(['msg' => 'Đăng nhập Google thất bại. Vui lòng thử lại.']);
        }
    }
}
