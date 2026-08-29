<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\CommissionLog;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class ProfileController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        if (request()->query('tab') === 'security') {
            return Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
                    ? [new Middleware('password.confirm')]
                    : [];
        }

        return [];
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($this->isEmployeeAccount($user)) {
            return redirect()->route('employee-portal.profile');
        }

        // 1. Profile data
        $mustVerifyEmail = $user instanceof MustVerifyEmail;
        $status = $request->session()->get('status');

        // 2. Security data
        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();
        $passwordRules = Password::defaults()->toPasswordRulesString();

        // 3. Referrals data
        $referrals = User::query()
            ->where('referred_by_id', $user->id)
            ->latest('id')
            ->get(['id', 'name', 'created_at', 'status'])
            ->map(fn ($u) => [
                'name' => $u->name,
                'created_at' => $u->created_at->format('d/m/Y H:i'),
                'status' => $u->status,
            ]);

        $commissionLogs = CommissionLog::query()
            ->where('user_id', $user->id)
            ->with(['buyer:id,name', 'restaurant:id,name'])
            ->latest('id')
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'buyer_name' => $log->buyer?->name ?? 'Người dùng ẩn',
                'restaurant_name' => $log->restaurant?->name ?? 'Doanh nghiệp',
                'amount' => (float) $log->amount,
                'commission_percentage' => (float) $log->commission_percentage,
                'commission_amount' => (float) $log->commission_amount,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ]);

        $withdrawalRequests = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->map(fn ($req) => [
                'id' => $req->id,
                'amount' => (float) $req->amount,
                'bank_name' => $req->bank_name,
                'bank_account_number' => $req->bank_account_number,
                'bank_account_name' => $req->bank_account_name,
                'status' => $req->status,
                'notes' => $req->notes,
                'created_at' => $req->created_at->format('d/m/Y H:i'),
            ]);

        $props = [
            'mustVerifyEmail' => $mustVerifyEmail,
            'status' => $status,
            'canManageTwoFactor' => $canManageTwoFactor,
            'passwordRules' => $passwordRules,
            'referrals' => $referrals,
            'commissionLogs' => $commissionLogs,
            'withdrawalRequests' => $withdrawalRequests,
            'success' => session('success'),
            'error' => session('error'),
        ];

        if ($canManageTwoFactor) {
            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Profile', $props);
    }

    private function isEmployeeAccount(User $user): bool
    {
        return ! $user->isOwner()
            && ! $user->isSuperAdmin()
            && $user->hasAnyRole([
                'cashier',
                'waiter',
                'kitchen',
                'inventory_staff',
                'shipper',
            ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Log out first. SessionGuard may rotate the remember token during
        // logout; doing that after model deletion would save the deleted model
        // again because the guard still holds the same instance.
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Update the user's mobile device token.
     */
    public function updateDeviceToken(Request $request): RedirectResponse
    {
        $request->validate([
            'device_token' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'device_token' => $request->input('device_token'),
        ]);

        return back()->with('success', 'Đã cập nhật token thiết bị.');
    }
}
