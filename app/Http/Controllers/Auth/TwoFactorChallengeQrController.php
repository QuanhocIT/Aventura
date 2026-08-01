<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Trả về QR Code SVG và khóa thiết lập thủ công cho người dùng đang trong
 * luồng xác thực 2FA (đã đăng nhập bằng mật khẩu, chưa xác nhận 2FA).
 *
 * Route này chỉ hoạt động khi session có `login.id` (tức là user đang bị chặn
 * tại trang two-factor-challenge), tránh lộ QR code ra ngoài.
 */
class TwoFactorChallengeQrController extends Controller
{
    public function __invoke(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $user = $this->challengedUser($request);

        if (! $user || ! $user->two_factor_secret) {
            return response()->json(['error' => 'Không tìm thấy dữ liệu 2FA.'], 404);
        }

        $secret = decrypt($user->two_factor_secret);

        $qrCodeUrl = $provider->qrCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        // Tạo QR code SVG từ URL otpauth://
        $qrCodeSvg = $this->generateQrSvg($qrCodeUrl);

        return response()->json([
            'qr_code' => $qrCodeSvg,
            'setup_key' => $secret,
            'otpauth_url' => $qrCodeUrl,
        ]);
    }

    private function challengedUser(Request $request): ?User
    {
        if (! $request->session()->has('login.id')) {
            return null;
        }

        return User::find($request->session()->get('login.id'));
    }

    /**
     * Tạo QR code SVG bằng thư viện BaconQrCode (đã được Fortify include sẵn).
     */
    private function generateQrSvg(string $url): string
    {
        try {
            // Fortify dùng BaconQrCode để render SVG — dùng lại luôn
            $svg = (new Writer(
                new ImageRenderer(
                    new RendererStyle(192, 0, null, null,
                        Fill::uniformColor(
                            new Rgb(255, 255, 255),
                            new Rgb(45, 55, 72)
                        )
                    ),
                    new SvgImageBackEnd
                )
            ))->writeString($url);

            return $svg;
        } catch (\Throwable $e) {
            // Fallback: trả về URL để frontend tự xử lý
            return '';
        }
    }
}
