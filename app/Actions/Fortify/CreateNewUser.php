<?php

namespace App\Actions\Fortify;

use App\Concerns\GeneratesSignedCaptcha;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\VerifiesTurnstile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Onboarding\RestaurantOnboardingService;
use App\Support\Tenant\TenantContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use GeneratesSignedCaptcha, PasswordValidationRules, ProfileValidationRules, VerifiesTurnstile;

    public function __construct(
        private readonly RestaurantOnboardingService $onboarding,
        private readonly TenantContext $tenantContext,
    ) {}

    public function create(array $input): User
    {
        if (! app()->runningUnitTests()) {
            $turnstileSiteKey = env('TURNSTILE_SITE_KEY') ?: SystemSetting::get('turnstile_site_key');
            if ($turnstileSiteKey) {
                $token = $input['cf-turnstile-response'] ?? null;
                if (! $token || ! $this->verifyTurnstile($token)) {
                    throw ValidationException::withMessages([
                        'email' => ['Vui lòng hoàn thành xác minh bảo mật Cloudflare Turnstile.'],
                    ]);
                }
            } else {
                $captchaAnswer = $input['captcha_answer'] ?? null;
                $captchaToken = $input['captcha_token'] ?? null;
                if (! $this->verifyCaptchaToken($captchaToken, $captchaAnswer)) {
                    throw ValidationException::withMessages([
                        'email' => ['Câu trả lời xác minh bảo mật không chính xác hoặc đã hết hạn.'],
                    ]);
                }
            }
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'restaurant_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{9,15}$/'],
            'plan_code' => ['nullable', 'string', 'max:50'],
            'password' => $this->passwordRules(),
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
        ], [
            'name.regex' => 'Họ và tên chỉ được nhập chữ cái và khoảng trắng.',
            'phone.regex' => 'Số điện thoại chỉ được chứa các chữ số (từ 9 đến 15 số).',
            'referral_code.exists' => 'Mã giới thiệu không tồn tại trong hệ thống.',
        ])->validate();

        $user = $this->onboarding->onboard($input);
        $this->tenantContext->setRestaurantId($user->restaurant_id);

        return $user;
    }
}
