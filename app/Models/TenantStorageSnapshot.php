<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStorageSnapshot extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'snapshot_at' => 'datetime',
            'media_bytes' => 'integer',
            'media_files' => 'integer',
            'database_rows' => 'integer',
            'database_bytes' => 'integer',
            'total_bytes' => 'integer',
            'growth_bytes' => 'integer',
            'table_stats' => 'array',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
