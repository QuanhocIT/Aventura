<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\SuperAdminAuditStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AccountController extends Controller
{
    private const ADMIN_SUB_ROLES = ['system_admin', 'billing_admin', 'support_specialist'];

    public function index(Request $request): Response
    {
        $superAdminRoles = config('auth.super_admin_roles', ['super_admin']);

        // Show both regular users AND admin sub-role accounts
        $query = User::with(['roles', 'restaurant']);

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

        $accounts = $query->latest()->paginate(10)->withQueryString();

        // Available admin sub-roles for the create/update forms
        $adminSubRoles = Role::whereIn('name', self::ADMIN_SUB_ROLES)
            ->pluck('name')
            ->toArray();

        return Inertia::render('super-admin/accounts/Index', [
            'accounts' => $accounts->through(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?? null,
                'status' => $u->status ?? 'active',
                'roles' => $u->roles->pluck('name'),
                'restaurant' => $u->restaurant?->name ?? '—',
                'restaurant_id' => $u->restaurant_id,
                'has_2fa' => ! is_null($u->two_factor_confirmed_at),
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
                'email_verified' => ! is_null($u->email_verified_at),
                'created_at' => $u->created_at?->format('d/m/Y') ?? '-',
            ]),
            'filters' => $request->only(['search', 'role', 'status']),
            'adminSubRoles' => $adminSubRoles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:'.implode(',', self::ADMIN_SUB_ROLES)],
        ]);

        $tempPassword = Str::random(10).rand(10, 99).'!';

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($tempPassword),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'created',
            'action' => 'create_admin_account',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => null,
            'new_values' => ['email' => $user->email, 'role' => $data['role']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()
            ->with('temp_password', $tempPassword)
            ->with('success', "Đã tạo tài khoản {$user->name} với vai trò {$data['role']}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Không thể thay đổi role của tài khoản Super Admin gốc.');
        }

        $data = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', self::ADMIN_SUB_ROLES)],
        ]);

        if (! $user->isSuperAdmin()) {
            return back()->with('error', 'Chi duoc thay doi role cho tai khoan platform-admin. Khong the nang owner/manager tenant thanh Superadmin.');
        }

        $oldRoles = $user->getRoleNames()->values()->all();

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'update_admin_role.before',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => ['roles' => $oldRoles],
            'new_values' => [
                'requested_role' => $data['role'],
                'target_email' => $user->email,
                'status' => 'pending',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Remove old admin sub-roles, assign new one
        DB::transaction(function () use ($data, $oldRoles, $user): void {
            $user->removeRole(array_intersect($oldRoles, self::ADMIN_SUB_ROLES));
            $user->assignRole($data['role']);
        });

        $user->refresh();

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => $request->user()->id,
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'update_admin_role.after',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => ['roles' => $oldRoles],
            'new_values' => [
                'roles' => $user->getRoleNames()->values()->all(),
                'requested_role' => $data['role'],
                'email' => $user->email,
                'status' => 'success',
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        SuperAdminAuditStream::record('update_admin_role', [
            'target_email' => $user->email,
            'old_roles' => $oldRoles,
            'new_roles' => $user->getRoleNames()->values()->all(),
        ], User::class, $user->id, 'warning');

        return back()->with('success', "Đã cập nhật vai trò của {$user->name} thành {$data['role']}.");
    }

    public function resetPassword(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Không thể reset mật khẩu tài khoản Super Admin gốc.');
        }

        $tempPassword = Str::random(10).rand(10, 99).'!';
        $user->forceFill(['password' => bcrypt($tempPassword)])->save();

        $this->writeAuditLog('reset_password', $user);

        return back()
            ->with('temp_password', $tempPassword)
            ->with('success', "Đã reset mật khẩu cho {$user->name}.");
    }

    public function disable2FA(User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Không thể tắt 2FA của tài khoản Super Admin gốc.');
        }

        if (is_null($user->two_factor_confirmed_at)) {
            return back()->with('error', 'Tài khoản này chưa bật 2FA.');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->writeAuditLog('disable_2fa', $user);

        return back()->with('success', "Đã tắt xác thực 2FA cho {$user->name}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Không thể thay đổi trạng thái tài khoản Super Admin gốc.');
        }

        $request->validate(['status' => 'required|in:active,suspended']);

        $old = $user->status ?? 'active';
        $user->forceFill(['status' => $request->status])->save();

        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => 'toggle_account_status',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'old_values' => ['status' => $old],
            'new_values' => ['status' => $request->status, 'user_email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $label = $request->status === 'active' ? 'kích hoạt' : 'tạm ngừng';

        return back()->with('success', "Đã {$label} tài khoản {$user->name}.");
    }

    private function writeAuditLog(string $action, User $subject): void
    {
        AuditLog::create([
            'restaurant_id' => null,
            'branch_id' => null,
            'user_id' => auth()->id(),
            'user_role' => 'admin',
            'event' => 'updated',
            'action' => $action,
            'subject_type' => User::class,
            'subject_id' => $subject->id,
            'old_values' => null,
            'new_values' => ['user_email' => $subject->email],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
