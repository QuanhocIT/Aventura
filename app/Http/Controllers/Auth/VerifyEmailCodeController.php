<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

class VerifyEmailCodeController extends Controller
{
    /**
     * Xác thực email bằng mã OTP 6 số được gửi cùng email với link xác nhận
     * (cách thay thế cho việc bấm link, dành cho người dùng thích nhập tay).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(Fortify::redirects('email-verification'));
        }

        if (! $user->email_verification_code || ! $user->email_verification_code_expires_at) {
            return back()->withErrors(['code' => 'Không tìm thấy mã xác thực nào đang hiệu lực. Vui lòng gửi lại email xác thực.']);
        }

        if (now()->greaterThan($user->email_verification_code_expires_at)) {
            $user->forceFill([
                'email_verification_code' => null,
                'email_verification_code_expires_at' => null,
            ])->save();

            return back()->withErrors(['code' => 'Mã xác thực đã hết hạn. Vui lòng bấm "Gửi lại email xác thực" để nhận mã mới.']);
        }

        if (! Hash::check($request->string('code'), $user->email_verification_code)) {
            return back()->withErrors(['code' => 'Mã xác thực không đúng. Vui lòng kiểm tra lại email mới nhất.']);
        }

        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
