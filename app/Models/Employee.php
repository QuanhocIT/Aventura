<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;

use Database\Factories\Hr\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use HasRoles;

    protected $guarded = [];

    protected string $guard_name = 'web';

    protected static function booted(): void
    {
        static::saved(function (self $employee): void {
            // Keep Spatie pivot table consistent with employees.role_id.
            if ($employee->role_id) {
                $employee->syncRoles([$employee->role_id]);

                return;
            }

            $employee->syncRoles([]);
        });

        static::deleted(function (self $employee): void {
            $employee->syncRoles([]);
        });
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduleAssignment::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(\App\Models\MediaAsset::class, 'attachable');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'employee_avatar');
    }

    public function citizenIdFront(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'citizen_id_front');
    }

    public function citizenIdBack(): MorphOne
    {
        return $this->morphOne(\App\Models\MediaAsset::class, 'attachable')
            ->where('collection', 'citizen_id_back');
    }

    protected static function newFactory(): Factory
    {
        return EmployeeFactory::new();
    }
}

