<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use BelongsToRestaurant;

    /** Mẫu dùng cho bàn giao ca thay vì checklist theo ngày. */
    public const TYPE_HANDOVER = 'handover';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Chi nhánh được áp mẫu này. Không gán chi nhánh nào = áp cho toàn chuỗi.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(RestaurantBranch::class, 'checklist_template_branch', 'template_id', 'branch_id')
            ->withTimestamps();
    }

    /**
     * Mẫu có hiệu lực tại một chi nhánh: hoặc được gán trực tiếp, hoặc là mẫu
     * toàn chuỗi (không gán chi nhánh nào).
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId): void {
            $q->whereHas('branches', fn (Builder $b) => $b->where('restaurant_branches.id', $branchId))
                ->orWhereDoesntHave('branches');
        });
    }

    public function scopeHandover(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_HANDOVER);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'template_id')->orderBy('sort_order');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChecklistCompletion::class, 'template_id');
    }
}
