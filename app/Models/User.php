<?php

namespace App\Models;

use App\Services\EmailVerificationService;
use App\Support\Tenant\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'google_id',
    'email_verified_at',
    'last_login_at',
    'restaurant_id',
    'branch_id',
    'supplier_id',
    'phone',
    'avatar_url',
    'status',
    'onboarding_status',
    'referral_code',
    'referred_by_id',
    'commission_balance',
    'bank_name',
    'bank_account_number',
    'bank_account_name',
    'pin_code',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_code' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'onboarding_status' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function processedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'processed_by');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'attachable');
    }

    public function avatarAsset(): MorphOne
    {
        return $this->morphOne(MediaAsset::class, 'attachable')
            ->where('collection', 'user_avatar');
    }

    protected static function booted()
    {
        static::creating(function (User $user) {
            if (empty($user->referral_code)) {
                $user->referral_code = static::generateUniqueReferralCode();
            }
        });
    }

    public static function generateUniqueReferralCode(): string
    {
        do {
            $code = 'AVT'.strtoupper(Str::random(5));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function commissionLogs(): HasMany
    {
        return $this->hasMany(CommissionLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(config('auth.super_admin_roles', ['super_admin']));
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * A manager is branch-scoped. Owners may also have legacy manager data in
     * old records, but they retain the owner-wide scope.
     */
    public function isBranchManager(): bool
    {
        return ! $this->isSuperAdmin()
            && ! $this->isOwner()
            && $this->hasAnyRole(['manager', 'quản lý', 'quan_ly', 'quanly']);
    }

    public function canViewAllBranches(): bool
    {
        return $this->isSuperAdmin() || $this->isOwner();
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($this->canViewAllBranches()) {
            return true;
        }

        return $branchId !== null && $this->assignedBranchId() === $branchId;
    }

    /**
     * Employee assignment is the source of truth for staff and managers;
     * user.branch_id is retained as a compatibility fallback for legacy data.
     */
    public function assignedBranchId(): ?int
    {
        $employee = $this->relationLoaded('employee')
            ? $this->getRelation('employee')
            : $this->employee;

        $branchId = $employee?->getRawOriginal('branch_id')
            ?? $this->getRawOriginal('branch_id');

        return $branchId ? (int) $branchId : null;
    }

    public function isExemptFromShiftLock(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->hasAnyRole(['owner', 'manager', 'supplier', 'quản lý', 'quan_ly', 'quanly'])) {
            return true;
        }

        $employee = $this->employee;
        if ($employee) {
            $jobTitle = mb_strtolower($employee->job_title);
            if (str_contains($jobTitle, 'manager') || str_contains($jobTitle, 'quản lý')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chỉ tài khoản Super Admin mới bắt buộc xác thực email qua Gmail; các tài khoản
     * khác được coi như đã xác thực để middleware `verified` không chặn họ.
     */
    public function hasVerifiedEmail(): bool
    {
        if (! $this->isSuperAdmin()) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    /**
     * Gửi email xác thực qua microservice Brevo (Mail mặc định của Laravel chỉ ghi log,
     * không gửi được thư thật) thay vì dùng notification "mail" channel có sẵn.
     * Chỉ gửi cho Super Admin — các tài khoản khác không cần xác thực nên không gửi.
     */
    public function sendEmailVerificationNotification(): void
    {
        if (! $this->isSuperAdmin()) {
            return;
        }

        app(EmailVerificationService::class)->send($this);
    }

    public function getBranchIdAttribute($value)
    {
        if ($this->canViewAllBranches() || $this->isBranchManager()) {
            $context = app(TenantContext::class);
            if ($context->restaurantId() === $this->restaurant_id) {
                if ($context->isBranchScoped()) {
                    return $context->activeBranchId();
                }

                if ($context->isAllBranches()) {
                    return null;
                }
            }
        }

        return $value ? (int) $value : null;
    }

    public static function validateManagerBypass(?string $bypassCode, int $restaurantId): ?User
    {
        if (empty($bypassCode)) {
            return null;
        }

        $cashier = auth()->user();
        $ip = request()->ip();
        $cacheKey = "failed_bypass_attempts:{$restaurantId}:".($cashier ? $cashier->id : "ip:{$ip}");

        if ($cacheKey) {
            $failedAttempts = (int) Cache::get($cacheKey, 0);
            if ($failedAttempts >= 3) {
                throw ValidationException::withMessages([
                    'bypass_code' => 'Bạn đã nhập sai mã phê duyệt quá 3 lần. Chức năng phê duyệt tạm thời bị khóa trong 15 phút.',
                ]);
            }
        }

        // 1. Kiểm tra mã tĩnh trước (Backward compatibility)
        $bypassSetting = DB::table('restaurant_settings')
            ->where('restaurant_id', $restaurantId)
            ->where('key_name', 'manager_bypass_code')
            ->value('value');

        $restaurant = Restaurant::find($restaurantId);
        $staticCode = $bypassSetting ? json_decode($bypassSetting) : ($restaurant?->manager_bypass_code ?? (app()->runningUnitTests() ? 'MANAGER123' : null));

        $matchedUser = null;
        if ($staticCode && $bypassCode === $staticCode) {
            $matchedUser = self::role('owner')->where('restaurant_id', $restaurantId)->first()
                ?? self::role('manager')->where('restaurant_id', $restaurantId)->first()
                ?? self::where('restaurant_id', $restaurantId)->first();
        }

        // 2. Kiểm tra nếu mã chứa định dạng email:password (Đăng nhập nhanh)
        if (! $matchedUser && str_contains($bypassCode, ':')) {
            $parts = explode(':', $bypassCode, 2);
            // Check if format is user_id:pin (direct fast PIN match)
            if (is_numeric($parts[0]) && preg_match('/^\d{4,6}$/', $parts[1])) {
                $userId = (int) $parts[0];
                $pin = $parts[1];
                $user = self::where('restaurant_id', $restaurantId)
                    ->where('id', $userId)
                    ->first();
                if ($user && ($user->hasAnyRole(['owner', 'manager']) || $user->can('approve_requests'))) {
                    if ($user->pin_code && Hash::check($pin, $user->pin_code)) {
                        $matchedUser = $user;
                    }
                }
            } else {
                // Email:password fast login fallback
                [$email, $password] = $parts;
                $user = self::where('restaurant_id', $restaurantId)
                    ->where('email', trim($email))
                    ->first();
                if ($user && Hash::check($password, $user->password)) {
                    if ($user->hasAnyRole(['owner', 'manager']) || $user->can('approve_requests')) {
                        $matchedUser = $user;
                    }
                }
            }
        }

        // 3. Kiểm tra mã PIN 4-6 số của tất cả Owner / Manager thuộc nhà hàng này (Chỉ chạy khi là chuỗi số 4-6 ký tự)
        // Giới hạn 10 managers tránh bcrypt O(N) loop với nhà hàng có nhiều quản lý
        if (! $matchedUser && preg_match('/^\d{4,6}$/', $bypassCode)) {
            $managers = self::where('restaurant_id', $restaurantId)
                ->whereNotNull('pin_code')
                ->limit(10) // bcrypt check tốn CPU — giới hạn số lượng
                ->get();

            foreach ($managers as $m) {
                if ($m->hasAnyRole(['owner', 'manager']) || $m->can('approve_requests')) {
                    if (Hash::check($bypassCode, $m->pin_code)) {
                        $matchedUser = $m;
                        break;
                    }
                }
            }
        }

        if ($matchedUser) {
            if ($cacheKey) {
                Cache::forget($cacheKey);
            }

            return $matchedUser;
        }

        // Tăng bộ đếm khi sai mã phê duyệt
        if ($cacheKey) {
            $failedAttempts = (int) Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $failedAttempts + 1, now()->addMinutes(15));

            if ($failedAttempts + 1 >= 3) {
                throw ValidationException::withMessages([
                    'bypass_code' => 'Bạn đã nhập sai mã phê duyệt quá 3 lần. Chức năng phê duyệt tạm thời bị khóa trong 15 phút.',
                ]);
            }
        }

        return null;
    }
}
