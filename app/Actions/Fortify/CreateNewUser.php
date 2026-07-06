<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Services\Onboarding\RestaurantOnboardingService;
use App\Support\Tenant\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly RestaurantOnboardingService $onboarding,
        private readonly TenantContext $tenantContext,
    ) {}

    public function create(array $input): User
    {
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
