<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Laravel\Scout\Searchable;

use Database\Factories\Restaurant\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToRestaurant;
    use HasFactory;
    use SoftDeletes;
    use Searchable;

    protected $guarded = [];

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'restaurant_id' => (int) $this->restaurant_id,
            'table_id' => (int) $this->table_id,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'total_amount' => (float) $this->total_amount,
            'notes' => $this->note,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}

