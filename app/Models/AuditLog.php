<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        return $this->belongsTo(\App\Models\User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Restaurant::class);
    }

    /**
     * Ghi nhật ký kiểm toán tĩnh một cách nhất quán.
     */
    public static function log(string $action, string $event, $subject, ?array $oldValues = null, ?array $newValues = null): self
    {
        $user = auth()->user();
        return self::create([
            'restaurant_id' => $user?->restaurant_id,
            'branch_id'     => $user?->branch_id,
            'user_id'       => $user?->id,
            'user_role'     => $user ? ($user->roles()->pluck('name')->first() ?? 'staff') : null,
            'event'         => $event,
            'action'        => $action,
            'subject_type'  => $subject ? get_class($subject) : null,
            'subject_id'    => $subject?->id,
            'old_values'    => $oldValues,
            'new_values'    => $newValues,
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
        ]);
    }
}
