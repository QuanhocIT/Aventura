<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $superAdminRoles = config('auth.super_admin_roles', ['admin', 'super_admin']);

        $query = User::with(['roles', 'restaurant'])
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', $superAdminRoles));

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            );
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $accounts = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('super-admin/accounts/Index', [
            'accounts' => $accounts->through(fn ($u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'phone'          => $u->phone ?? null,
                'status'         => $u->status ?? 'active',
                'roles'          => $u->roles->pluck('name'),
                'restaurant'     => $u->restaurant?->name ?? 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â',
                'restaurant_id'  => $u->restaurant_id,
                'has_2fa'        => ! is_null($u->two_factor_confirmed_at),
                'last_login_at'  => $u->last_login_at?->format('d/m/Y H:i'),
                'email_verified' => ! is_null($u->email_verified_at),
                'created_at'     => $u->created_at?->format('d/m/Y') ?? '-',
            ]),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'KhÃƒÆ’Ã‚Â´ng thÃƒÂ¡Ã‚Â»Ã†â€™ reset mÃƒÂ¡Ã‚ÂºÃ‚Â­t khÃƒÂ¡Ã‚ÂºÃ‚Â©u tÃƒÆ’Ã‚Â i khoÃƒÂ¡Ã‚ÂºÃ‚Â£n Super Admin.');
        }

        $tempPassword = Str::random(10) . rand(10, 99) . '!';
        $user->forceFill(['password' => bcrypt($tempPassword)])->save();

        $this->writeAuditLog('reset_password', $user);

        return back()
            ->with('temp_password', $tempPassword)
            ->with('success', "Ãƒâ€žÃ‚ÂÃƒÆ’Ã‚Â£ reset mÃƒÂ¡Ã‚ÂºÃ‚Â­t khÃƒÂ¡Ã‚ÂºÃ‚Â©u cho {$user->name}.");
    }

    public function disable2FA(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'KhÃƒÆ’Ã‚Â´ng thÃƒÂ¡Ã‚Â»Ã†â€™ tÃƒÂ¡Ã‚ÂºÃ‚Â¯t 2FA cÃƒÂ¡Ã‚Â»Ã‚Â§a tÃƒÆ’Ã‚Â i khoÃƒÂ¡Ã‚ÂºÃ‚Â£n Super Admin.');
        }

        if (is_null($user->two_factor_confirmed_at)) {
            return back()->with('error', 'TÃƒÆ’Ã‚Â i khoÃƒÂ¡Ã‚ÂºÃ‚Â£n nÃƒÆ’Ã‚Â y chÃƒâ€ Ã‚Â°a bÃƒÂ¡Ã‚ÂºÃ‚Â­t 2FA.');
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        $this->writeAuditLog('disable_2fa', $user);

        return back()->with('success', "Ãƒâ€žÃ‚ÂÃƒÆ’Ã‚Â£ tÃƒÂ¡Ã‚ÂºÃ‚Â¯t xÃƒÆ’Ã‚Â¡c thÃƒÂ¡Ã‚Â»Ã‚Â±c 2FA cho {$user->name}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'KhÃƒÆ’Ã‚Â´ng thÃƒÂ¡Ã‚Â»Ã†â€™ thay Ãƒâ€žÃ¢â‚¬ËœÃƒÂ¡Ã‚Â»Ã¢â‚¬Â¢i trÃƒÂ¡Ã‚ÂºÃ‚Â¡ng thÃƒÆ’Ã‚Â¡i tÃƒÆ’Ã‚Â i khoÃƒÂ¡Ã‚ÂºÃ‚Â£n Super Admin.');
        }

        $request->validate(['status' => 'required|in:active,suspended']);

        $old = $user->status ?? 'active';
        $user->forceFill(['status' => $request->status])->save();

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => auth()->id(),
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => 'toggle_account_status',
            'subject_type'  => User::class,
            'subject_id'    => $user->id,
            'old_values'    => ['status' => $old],
            'new_values'    => ['status' => $request->status, 'user_email' => $user->email],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        $label = $request->status === 'active' ? 'kÃƒÆ’Ã‚Â­ch hoÃƒÂ¡Ã‚ÂºÃ‚Â¡t' : 'tÃƒÂ¡Ã‚ÂºÃ‚Â¡m ngÃƒâ€ Ã‚Â°ng';

        return back()->with('success', "Ãƒâ€žÃ‚ÂÃƒÆ’Ã‚Â£ {$label} tÃƒÆ’Ã‚Â i khoÃƒÂ¡Ã‚ÂºÃ‚Â£n {$user->name}.");
    }

    private function writeAuditLog(string $action, User $subject): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id'     => null,
            'user_id'       => auth()->id(),
            'user_role'     => 'admin',
            'event'         => 'updated',
            'action'        => $action,
            'subject_type'  => User::class,
            'subject_id'    => $subject->id,
            'old_values'    => null,
            'new_values'    => ['user_email' => $subject->email],
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);
    }
}
