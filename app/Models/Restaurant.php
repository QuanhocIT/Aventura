<?php

namespace App\Models;

use Database\Factories\Tenant\RestaurantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Restaurant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subscription_started_at' => 'datetime',
            'subscription_ends_at'    => 'datetime',
            'trial_ends_at'           => 'datetime',
            'grace_period_minutes'    => 'integer',
            'ot_multiplier'           => 'float',
            'latitude'                => 'float',
            'longitude'               => 'float',
            'checkin_radius_meters'   => 'integer',
            'qr_checkin_expires_at'   => 'datetime',
            'last_active_at'          => 'datetime',
            'inactive_flagged_at'     => 'datetime',
            'is_inactive_flagged'     => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(RestaurantBranch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function revenueSummaries(): HasMany
    {
        return $this->hasMany(\App\Models\RestaurantRevenueSummary::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(RestaurantSubscription::class);
    }

    public function dailyChecks(): HasMany
    {
        return $this->hasMany(RestaurantDailyCheck::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function billingAdjustments(): HasMany
    {
        return $this->hasMany(BillingAdjustment::class);
    }

    public function shiftSwaps(): HasMany
    {
        return $this->hasMany(ShiftSwap::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(RestaurantSubscription::class)
            ->whereIn('status', ['trial', 'active'])
            ->latestOfMany();
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\MediaAsset::class, 'attachable');
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isSuspended(): bool { return $this->status === 'suspended'; }
    public function isExpired(): bool { return $this->status === 'expired'; }

    public function currentAccessStatus(): string
    {
        if ($this->status === 'suspended') {
            return 'suspended';
        }

        $subscriptionEnd = $this->subscription_ends_at instanceof Carbon
            ? $this->subscription_ends_at
            : ($this->subscription_ends_at ? Carbon::parse($this->subscription_ends_at) : null);

        if ($this->status === 'expired' || ($subscriptionEnd && $subscriptionEnd->isPast())) {
            return 'expired';
        }

        return 'active';
    }

    protected static function newFactory(): Factory
    {
        return RestaurantFactory::new();
    }
}
