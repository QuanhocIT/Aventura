<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseFraudCase extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'warehouse_fraud_cases';

    protected $guarded = [];

    const STATUS_OPEN = 'open';

    const STATUS_INVESTIGATING = 'investigating';

    const STATUS_RESOLVED = 'resolved';

    const STATUS_CLOSED = 'closed';

    const STATUS_APPEALED = 'appealed';

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
            'evidence_urls' => 'array',
            'metadata' => 'array',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
