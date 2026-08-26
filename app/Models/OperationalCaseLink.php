<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalCaseLink extends Model
{
    use BelongsToRestaurant;
    use HasFactory;

    protected $guarded = [];

    public function report(): BelongsTo
    {
        return $this->belongsTo(OperationalInfringementReport::class, 'operational_report_id');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(OperationalInspection::class, 'operational_inspection_id');
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
