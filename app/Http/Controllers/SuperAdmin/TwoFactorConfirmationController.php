<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Middleware\RequireSuperAdminStepUp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SuperAdminAuditStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorConfirmationController extends Controller
{
    private const VALIDITY_MINUTES = 15;

    public function show(): Response
    {
        return Inertia::render('super-admin/security/ConfirmTwoFactor', [
            'validityMinutes' => self::VALIDITY_MINUTES,
        ]);
    }

    public function verify(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Vui lòng nhập mã 2FA.',
            'code.digits' => 'Mã 2FA phải gồm 6 chữ số.',
        ]);

        $user = $request->user();
        $secret = $user?->two_factor_secret;

        try {
            $secret = $secret ? decrypt($secret) : null;
            $valid = is_string($secret) && $provider->verify($secret, $data['code']);
        } catch (\Throwable $exception) {
            report($exception);
            $valid = false;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Mã 2FA không hợp lệ hoặc đã hết hạn.',
            ]);
        }

        $expiresAt = now()->addMinutes(self::VALIDITY_MINUTES);
        $request->session()->put(RequireSuperAdminStepUp::sessionKey(), $expiresAt->timestamp);
        $request->session()->put(RequireSuperAdminStepUp::sessionUserKey(), $user->id);

        SuperAdminAuditStream::record('superadmin_2fa_step_up', [
            'expires_at' => $expiresAt->toIso8601String(),
            'validity_minutes' => self::VALIDITY_MINUTES,
        ], User::class, $user?->id, 'warning');

        return redirect()
            ->route('superadmin.dashboard')
            ->with('success', 'Đã xác nhận 2FA. Bạn có thể thực hiện thao tác nhạy cảm trong 15 phút.');
    }
}
