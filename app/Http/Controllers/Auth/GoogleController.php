<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
// ...existing code...
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Exception;
// ...existing code...
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
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
// ...existing code...

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

<<<<<<< HEAD
            $user = User::updateOrCreate([
                'email' => $googleUser->getEmail(),
            ], [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            Auth::login($user);

            // Nếu user có role admin thì chuyển về dashboard super admin
            if ($user->hasRole('admin')) {
                return redirect()->intended('/super-admin/dashboard');
            }
            // Các user khác về dashboard thường
            return redirect()->intended('/dashboard');
        } catch (Exception $e) {
            return redirect('/login')->withErrors(['msg' => 'Đăng nhập Google thất bại!']);
=======
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
>>>>>>> origin/feature/duongnguyen26
        }
    }
}
