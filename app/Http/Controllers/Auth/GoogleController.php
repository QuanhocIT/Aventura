<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\CustomLoginResponse;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $googleEmail = $googleUser->getEmail();

            if (! $googleEmail) {
                return redirect('/login')->withErrors(['msg' => 'Dang nhap Google that bai. Tai khoan Google khong co email hop le.']);
            }

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleEmail)
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
                    'name' => $googleUser->getName() ?: $googleEmail,
                    'email' => $googleEmail,
                    'password' => Hash::make(Str::random(40)),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                ]);
            }

            Auth::login($user, true);

            return CustomLoginResponse::redirectForUser($user);
        } catch (Exception $exception) {
            Log::error('Google login failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            return redirect('/login')->withErrors(['msg' => 'Dang nhap Google that bai. Vui long thu lai.']);
        }
    }
}
