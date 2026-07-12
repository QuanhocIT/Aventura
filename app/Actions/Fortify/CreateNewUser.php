<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\VerifiesTurnstile;
use App\Concerns\GeneratesSignedCaptcha;
use App\Services\Onboarding\RestaurantOnboardingService;
use App\Support\Tenant\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules, VerifiesTurnstile, GeneratesSignedCaptcha;

    public function __construct(
        private readonly RestaurantOnboardingService $onboarding,
        private readonly TenantContext $tenantContext,
    ) {}

    public function create(array $input): User
    {
        if (!app()->runningUnitTests()) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: \App\Models\SystemSetting::get('turnstile_site_key');
            if ($turnstileSiteKey) {
                $token = $input['cf-turnstile-response'] ?? null;
                if (!$token || !$this->verifyTurnstile($token)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => ['Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.'],
                    ]);
                }
            } else {
                $captchaAnswer = $input['captcha_answer'] ?? null;
                $captchaToken = $input['captcha_token'] ?? null;
                if (!$this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => ['Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.'],
                    ]);
                }
            }
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'restaurant_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'plan_code' => ['nullable', 'string', 'max:50'],
            'password' => $this->passwordRules(),
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
        ], [
            'referral_code.exists' => 'Mã giới thiệu không tồn tại trong hệ thống.',
        ])->validate();

        $user = $this->onboarding->onboard($input);
        $this->tenantContext->setRestaurantId($user->restaurant_id);

        return $user;
    }
}
