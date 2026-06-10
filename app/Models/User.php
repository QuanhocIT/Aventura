<?php

namespace App\Models;

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
        return $this->morphMany(\App\Models\MediaAsset::class, 'attachable');
    }

    public function avatarAsset(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
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
            $code = 'AVT' . strtoupper(\Illuminate\Support\Str::random(5));
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

        app(\App\Services\EmailVerificationService::class)->send($this);
    }
}

