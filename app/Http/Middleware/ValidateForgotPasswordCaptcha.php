<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Concerns\VerifiesTurnstile;
use App\Concerns\GeneratesSignedCaptcha;

class ValidateForgotPasswordCaptcha
{
    use VerifiesTurnstile, GeneratesSignedCaptcha;

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
        if ($turnstileSiteKey) {
            $token = $request->input('cf-turnstile-response');
            if (!$token || !$this->verifyTurnstile($token)) {
                throw ValidationException::withMessages([
                    'email' => ['Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.'],
                ]);
            }
        } else {
            $captchaAnswer = $request->input('captcha_answer');
            $captchaToken = $request->input('captcha_token');
            if (!$this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                throw ValidationException::withMessages([
                    'email' => ['Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.'],
                ]);
            }
        }

        return $next($request);
    }
}
