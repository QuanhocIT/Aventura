<?php

namespace App\Models;

use App\Support\Tenant\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->branch_id)) {
                $targetBranchId = null;

                if ($model->subject_type && $model->subject_id && class_exists($model->subject_type)) {
                    try {
                        $subject = ($model->subject_type)::find($model->subject_id);
                        $targetBranchId = self::resolveBranchId($subject);
                    } catch (\Throwable $e) {
                        // ignore lookup errors
                    }
                }

                $model->branch_id = $targetBranchId
                    ?? ($model->new_values['branch_id'] ?? null)
                    ?? ($model->old_values['branch_id'] ?? null)
                    ?? app(TenantContext::class)->activeBranchId()
                    ?? (static function (): ?int {
                        /** @var \App\Models\User|null $u */
                        $u = Auth::user();
                        return $u?->branch_id;
                    })();
            }
        });
    }

    /**
     * Ghi nhật ký kiểm toán tĩnh một cách nhất quán.
     */
    public static function log(string $action, string $event, $subject, ?array $oldValues = null, ?array $newValues = null): self
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        $branchId = self::resolveBranchId($subject)
            ?? ($newValues['branch_id'] ?? null)
            ?? ($oldValues['branch_id'] ?? null)
            ?? app(TenantContext::class)->activeBranchId()
            ?? $user?->branch_id;

        return self::create([
            'restaurant_id' => $user?->restaurant_id ?? ($subject && isset($subject->restaurant_id) ? $subject->restaurant_id : null),
            'branch_id' => $branchId,
            'user_id' => $user?->id,
            'user_role' => $user ? ($user->roles()->pluck('name')->first() ?? 'staff') : null,
            'event' => $event,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private static function resolveBranchId(mixed $subject, int $depth = 0): ?int
    {
        if (! is_object($subject) || $depth > 3) {
            return null;
        }

        if ($subject instanceof RestaurantBranch) {
            return $subject->id ? (int) $subject->id : null;
        }

        if (isset($subject->branch_id) && $subject->branch_id !== null) {
            return (int) $subject->branch_id;
        }

        if (method_exists($subject, 'getBranchId')) {
            try {
                $branchId = $subject->getBranchId();
                if ($branchId !== null) {
                    return (int) $branchId;
                }
            } catch (\Throwable $e) {
                // Continue with related models.
            }
        }

        foreach (['branch', 'report', 'inspection', 'correctiveAction', 'asset', 'handover'] as $relation) {
            if (! method_exists($subject, $relation)) {
                continue;
            }

            try {
                $branchId = self::resolveBranchId($subject->{$relation}, $depth + 1);
                if ($branchId !== null) {
                    return $branchId;
                }
            } catch (\Throwable $e) {
                // A missing relation should not prevent the audit event from being written.
            }
        }

        return null;
    }
}
