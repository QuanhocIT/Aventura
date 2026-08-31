<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecurityController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
            'activation_token' => null,
            'activation_expires_at' => null,
        ]);
        $user->increment('security_session_version');
        $request->session()->put('security_session_version', (int) $user->fresh()->security_session_version);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Update the user's PIN code.
     */
    public function updatePin(Request $request): RedirectResponse
    {
        $request->validate([
            'pin_code' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
        ]);

        $request->user()->update([
            'pin_code' => $request->pin_code,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã cập nhật mã PIN phê duyệt thành công.']);

        return back()->with('success', 'Đã cập nhật mã PIN phê duyệt thành công.');
    }
}
