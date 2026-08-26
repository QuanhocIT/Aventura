<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalEvidence extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $table = 'operational_evidence';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(OperationalInspection::class, 'operational_inspection_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(OperationalInfringementReport::class, 'operational_report_id');
    }

    public function correctiveAction(): BelongsTo
    {
        return $this->belongsTo(OperationalCorrectiveAction::class, 'corrective_action_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
