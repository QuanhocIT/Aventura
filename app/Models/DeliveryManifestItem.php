<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryManifestItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function deliveryManifest(): BelongsTo
    {
        return $this->belongsTo(DeliveryManifest::class, 'delivery_manifest_id');
    }

    public function supplyRequest(): BelongsTo
    {
        return $this->belongsTo(SupplyRequest::class, 'supply_request_id');
    }
}
