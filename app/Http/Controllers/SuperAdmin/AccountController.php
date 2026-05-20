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
        $query = User::with(['roles', 'restaurant'])
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['admin']));

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
                'restaurant'     => $u->restaurant?->name ?? '—',
                'restaurant_id'  => $u->restaurant_id,
                'has_2fa'        => ! is_null($u->two_factor_confirmed_at),
                'last_login_at'  => $u->last_login_at?->format('d/m/Y H:i'),
                'email_verified' => ! is_null($u->email_verified_at),
                'created_at'     => $u->created_at->format('d/m/Y'),
            ]),
            'filters' => $request->only(['search', 'role', 'status']),
        ]);
    }

    public function resetPassword(User $user): RedirectResponse
    {
        if ($user->hasRole('admin')) {
            return back()->with('error', 'Không thể reset mật khẩu tài khoản Super Admin.');
        }

        $tempPassword = Str::random(10) . rand(10, 99) . '!';
        $user->forceFill(['password' => bcrypt($tempPassword)])->save();

        $this->writeAuditLog('reset_password', $user);

        return back()
            ->with('temp_password', $tempPassword)
            ->with('success', "Đã reset mật khẩu cho {$user->name}.");
    }

    public function disable2FA(User $user): RedirectResponse
    {
        if ($user->hasRole('admin')) {
            return back()->with('error', 'Không thể tắt 2FA của tài khoản Super Admin.');
        }

        if (is_null($user->two_factor_confirmed_at)) {
            return back()->with('error', 'Tài khoản này chưa bật 2FA.');
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        $this->writeAuditLog('disable_2fa', $user);

        return back()->with('success', "Đã tắt xác thực 2FA cho {$user->name}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('admin')) {
            return back()->with('error', 'Không thể thay đổi trạng thái tài khoản Super Admin.');
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

        $label = $request->status === 'active' ? 'kích hoạt' : 'tạm ngưng';

        return back()->with('success', "Đã {$label} tài khoản {$user->name}.");
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
