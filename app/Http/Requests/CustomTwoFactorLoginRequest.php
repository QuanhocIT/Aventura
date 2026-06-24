<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class CustomTwoFactorLoginRequest extends TwoFactorLoginRequest
{
    /**
     * Accept either a valid TOTP authenticator code or a valid emailed OTP code.
     */
    public function hasValidCode()
    {
        return parent::hasValidCode() || $this->hasValidEmailCode();
    }

    protected function hasValidEmailCode(): bool
    {
        $user = $this->challengedUser();

        if (! $this->code
            || ! $user->two_factor_email_code
            || ! $user->two_factor_email_code_expires_at
        ) {
            return false;
        }

        if (now()->greaterThan($user->two_factor_email_code_expires_at)) {
            // Mã đã hết hạn: dọn sạch để tránh nhầm lẫn và báo rõ nguyên nhân,
            // thay vì để rơi vào thông báo "mã không đúng" chung chung gây khó hiểu.
            $user->forceFill([
                'two_factor_email_code' => null,
                'two_factor_email_code_expires_at' => null,
            ])->save();

            throw ValidationException::withMessages([
                'code' => ['Mã xác thực qua email đã hết hạn. Vui lòng bấm "Gửi mã qua email" để nhận mã mới.'],
            ]);
        }

        $valid = Hash::check($this->code, $user->two_factor_email_code);

        if ($valid) {
            $user->forceFill([
                'two_factor_email_code' => null,
                'two_factor_email_code_expires_at' => null,
            ])->save();

            $this->session()->forget('login.id');
        }

        return $valid;
    }
}
