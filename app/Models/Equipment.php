<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use BelongsToRestaurant;

    protected $table = 'equipment';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceLog::class)->latest();
    }

    public function warrantyExpiry(): ?string
    {
        if (! $this->purchase_date || ! $this->warranty_months) {
            return null;
        }

        return $this->purchase_date->addMonths($this->warranty_months)->toDateString();
    }

    public function isUnderWarranty(): bool
    {
        $expiry = $this->warrantyExpiry();

        return $expiry && $expiry >= now()->toDateString();
    }

    public function ageInMonths(): ?int
    {
        return $this->purchase_date ? (int) $this->purchase_date->diffInMonths(now()) : null;
    }

    public function totalMaintenanceCost(): float
    {
        return (float) $this->maintenanceLogs()->where('status', 'completed')->sum('cost');
    }
}
