<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\EmailMicroserviceClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorEmailCodeController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    public function store(Request $request, EmailMicroserviceClient $emailClient): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $throttleKey = 'two-factor-email-otp:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            // Đây không phải lỗi thật — mã đã được gửi ở lượt trước (vd: trang tự động
            // gửi lại khi tải lại/điều hướng quay lại). Dùng kênh 'info' trung tính thay vì
            // 'error' để tránh hiển thị cảnh báo đỏ gây hoang mang cho người dùng.
            return back()
                ->with('info', "Mã xác thực đã được gửi trước đó, vui lòng kiểm tra hộp thư của bạn. Có thể gửi lại sau {$seconds} giây.")
                ->with('retry_after', $seconds);
        }

        $code = (string) random_int(100000, 999999);

        // Chỉ ghi đè mã hiện tại trong DB SAU KHI gửi email thành công — tránh trường hợp
        // một lần gửi lại thất bại lại huỷ mất mã hợp lệ đã gửi thành công trước đó
        // (vd: mã cũ đang nằm trong hộp thư/spam của người dùng nhưng vẫn còn hạn dùng).
        $sent = $emailClient->sendOtp([
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'code' => $code,
            'expires_in_minutes' => self::CODE_TTL_MINUTES,
        ]);

        if (! $sent) {
            return back()->with('error', 'Không thể gửi email lúc này. Vui lòng thử lại sau hoặc dùng ứng dụng Authenticator.');
        }

        RateLimiter::hit($throttleKey, 60);

        $user->forceFill([
            'two_factor_email_code' => Hash::make($code),
            'two_factor_email_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        return back()->with('success', "Mã xác thực đã được gửi đến {$this->maskEmail($user->email)}. Mã có hiệu lực trong ".self::CODE_TTL_MINUTES.' phút.');
    }

    private function challengedUser(Request $request): ?User
    {
        if (! $request->session()->has('login.id')) {
            return null;
        }

        return User::find($request->session()->get('login.id'));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($local === '') {
            return $email;
        }

        $visible = mb_substr($local, 0, 2);

        return $visible.str_repeat('*', max(mb_strlen($local) - 2, 1)).'@'.$domain;
    }
}
