<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class EmailVerificationService
{
    public const CODE_TTL_MINUTES = 30;

    private const LINK_TTL_MINUTES = 60;

    public function __construct(private readonly EmailMicroserviceClient $emailClient) {}

    /**
     * Tạo đồng thời 1 link xác nhận (bấm vào là xong) và 1 mã OTP 6 số
     * (gõ tay), gửi cả hai trong cùng một email — người dùng chọn cách nào tiện hơn.
     */
    public function send(User $user): bool
    {
        $code = (string) random_int(100000, 999999);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::LINK_TTL_MINUTES),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // Chỉ ghi mã mới vào DB SAU KHI gửi email thành công — tránh trường hợp
        // một lần gửi lại thất bại lại huỷ mất mã hợp lệ đã gửi thành công trước đó.
        $sent = $this->emailClient->sendVerification([
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'verification_url' => $verificationUrl,
            'code' => $code,
            'code_expires_in_minutes' => self::CODE_TTL_MINUTES,
            'link_expires_in_minutes' => self::LINK_TTL_MINUTES,
        ]);

        if (! $sent) {
            return false;
        }

        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        return true;
    }
}
